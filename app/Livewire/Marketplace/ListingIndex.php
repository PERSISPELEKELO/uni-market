<?php

namespace App\Livewire\Marketplace;

use App\Models\Category;
use App\Models\Listing;
use App\Models\RecommendationEvent;
use App\Models\SavedSearch;
use App\Models\SearchLog;
use App\Services\Insights\BuyerInsightsService;
use App\Services\Recommendations\PersonalizedFeedService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class ListingIndex extends Component
{
    use WithPagination;

    private const SORTS = ['latest', 'price_asc', 'price_desc'];

    private const INITIAL_PER_PAGE = 12;

    private const PER_PAGE_INCREMENT = 12;

    public int $perPage = self::INITIAL_PER_PAGE;

    public string $search = '';

    public ?int $selectedCategory = null;

    public string $conditionFilter = '';

    public string $sortBy = 'latest';

    public bool $hotDealsOnly = false;

    public string $priceMin = '';

    public string $priceMax = '';

    /**
     * Guards recommendation-impression logging to once per page load -
     * render() runs again on every filter/search interaction, and an
     * impression is only ever genuinely "shown" the first time.
     */
    public bool $recommendationImpressionsLogged = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategory' => ['except' => null, 'as' => 'category'],
        'conditionFilter' => ['except' => '', 'as' => 'condition'],
        'sortBy' => ['except' => 'latest'],
        'hotDealsOnly' => ['except' => false, 'as' => 'deals'],
        'priceMin' => ['except' => '', 'as' => 'min'],
        'priceMax' => ['except' => '', 'as' => 'max'],
    ];

    /**
     * Overrides WithPagination's resetPage() so that every existing call
     * site that resets the page on a filter change (search, category,
     * condition, sort, etc.) also collapses the "Load more" grid back to
     * its first page - otherwise a filter change while several pages were
     * loaded would silently keep showing far more items than intended.
     */
    public function resetPage($pageName = 'page'): void
    {
        $this->setPage(1, $pageName);
        $this->perPage = self::INITIAL_PER_PAGE;
    }

    /**
     * "Load more" instead of numbered pagination links - grows the page
     * size rather than navigating to a separate page, so items already on
     * screen stay in place.
     */
    public function loadMore(): void
    {
        $this->perPage += self::PER_PAGE_INCREMENT;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Logged at most every 60 seconds per session, and only for a genuine
     * (3+ character) term - feeds MarketInsightsService's "top searches
     * with few or no results" (unmet demand).
     */
    public function updatedSearch(string $value): void
    {
        $term = strtolower(trim($value));

        if (mb_strlen($term) < 3) {
            return;
        }

        $throttleKey = 'search-log|'.session()->getId();

        if (RateLimiter::tooManyAttempts($throttleKey, 20)) {
            return;
        }

        RateLimiter::hit($throttleKey, 60);

        SearchLog::create([
            'term' => mb_substr($term, 0, 100),
            'results_count' => Listing::query()->active()->search($term)->count(),
            'user_id' => Auth::id(),
        ]);
    }

    public function updatingSortBy(): void
    {
        $this->resetPage();
    }

    /**
     * Accepts a loose value (not strictly ?int) since the sidebar's
     * <select> sends it through as a string, including an empty string
     * for "All categories" - normalised here rather than relying on PHP's
     * implicit string-to-int coercion for an empty string, which throws.
     */
    public function selectCategory(mixed $categoryId): void
    {
        $categoryId = ($categoryId === null || $categoryId === '') ? null : (int) $categoryId;

        $this->selectedCategory = ($this->selectedCategory === $categoryId) ? null : $categoryId;
        $this->resetPage();
    }

    public function toggleHotDeals(): void
    {
        $this->hotDealsOnly = ! $this->hotDealsOnly;
        $this->resetPage();
    }

    public function setCondition(string $condition): void
    {
        $this->conditionFilter = ($this->conditionFilter === $condition) ? '' : $condition;
        $this->resetPage();
    }

    public function updatedConditionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedHotDealsOnly(): void
    {
        $this->resetPage();
    }

    public function updatedPriceMin(): void
    {
        $this->resetPage();
    }

    public function updatedPriceMax(): void
    {
        $this->resetPage();
    }

    public function clearPriceRange(): void
    {
        $this->reset('priceMin', 'priceMax');
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'selectedCategory', 'conditionFilter', 'sortBy', 'hotDealsOnly', 'priceMin', 'priceMax');
        $this->resetPage();
    }

    /**
     * The marketplace empty state's "Get notified when items arrive"
     * secondary CTA - a guest is sent to log in first (per the brief: "only
     * ask guests to log in when they click"), a signed-in student just
     * flips the flag that CreateListing::persist() checks when a new
     * listing goes live.
     */
    public function toggleNotifyNewListings()
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        $user = Auth::user();
        $user->update(['notify_new_listings' => ! $user->notify_new_listings]);

        $this->dispatch('notify', type: 'success', message: $user->notify_new_listings
            ? "You'll be notified when new items are listed."
            : 'You will no longer be notified about new listings.');

        return null;
    }

    /**
     * Saves the student's current search/category/condition as a standing
     * alert - SavedSearchMatchFound (via saved-searches:notify-matches)
     * tells them when something new matches it. A no-op if there's nothing
     * to save, or if they've already saved this exact combination.
     */
    public function saveSearch()
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        $term = trim($this->search) !== '' ? trim($this->search) : null;
        $condition = array_key_exists($this->conditionFilter, Listing::CONDITIONS) ? $this->conditionFilter : null;

        if ($term === null && $this->selectedCategory === null && $condition === null) {
            return null;
        }

        $existing = SavedSearch::where('user_id', Auth::id())
            ->where('term', $term)
            ->where('category_id', $this->selectedCategory)
            ->where('condition', $condition)
            ->exists();

        if ($existing) {
            $this->dispatch('notify', type: 'success', message: "You've already saved this search.");

            return;
        }

        SavedSearch::create([
            'user_id' => Auth::id(),
            'term' => $term,
            'category_id' => $this->selectedCategory,
            'condition' => $condition,
            'created_at' => now(),
        ]);

        $this->dispatch('notify', type: 'success', message: "Saved. We'll let you know when something new matches.");
    }

    public function render()
    {
        $condition = array_key_exists($this->conditionFilter, Listing::CONDITIONS) ? $this->conditionFilter : '';
        $sortBy = in_array($this->sortBy, self::SORTS, true) ? $this->sortBy : 'latest';
        $priceMin = is_numeric($this->priceMin) ? (float) $this->priceMin : null;
        $priceMax = is_numeric($this->priceMax) ? (float) $this->priceMax : null;

        $listings = Listing::query()
            ->with(['seller:id,name,is_verified,avatar_path', 'category:id,name'])
            ->withCount(['reservations as active_reservations_count' => fn ($query) => $query->active()])
            ->active()
            ->search($this->search)
            ->when($this->selectedCategory, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($condition !== '', fn ($query) => $query->where('condition', $condition))
            ->when($this->hotDealsOnly, fn ($query) => $query->hotDeals())
            ->when($priceMin !== null, fn ($query) => $query->where('price', '>=', $priceMin))
            ->when($priceMax !== null, fn ($query) => $query->where('price', '<=', $priceMax))
            ->when($sortBy === 'price_asc', fn ($query) => $query->orderBy('price'))
            ->when($sortBy === 'price_desc', fn ($query) => $query->orderByDesc('price'))
            ->when($sortBy === 'latest', fn ($query) => $query->latest())
            ->paginate($this->perPage);

        $categories = Category::withCount(['listings' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->get();

        $hasActiveFilters = $this->search !== '' || $this->selectedCategory !== null || $condition !== '' || $sortBy !== 'latest' || $this->hotDealsOnly || $priceMin !== null || $priceMax !== null;

        $feed = app(PersonalizedFeedService::class);
        $user = Auth::user();

        // Carousels are only shown on the unfiltered homepage - once someone
        // is filtering, the main grid below already shows matching results.
        // Each is wrapped so a failure in one recommendation section never
        // takes down the whole home page - it just hides that section.
        $recommendedForYou = $hasActiveFilters ? collect() : $this->safely(fn () => $feed->recommendedForYou($user), collect());
        $becauseYouViewed = $hasActiveFilters ? null : $this->safely(fn () => $feed->becauseYouViewed($user), null);
        $trending = $hasActiveFilters ? collect() : $this->safely(fn () => $feed->trending(), collect());
        $goodDeals = $hasActiveFilters ? collect() : $this->safely(fn () => $feed->goodDeals(), collect());
        $justListed = $hasActiveFilters ? collect() : $this->safely(fn () => $feed->justListed(), collect());

        if (! $hasActiveFilters) {
            $this->logImpressionsOnce([
                RecommendationEvent::SECTION_RECOMMENDED_FOR_YOU => $recommendedForYou,
                RecommendationEvent::SECTION_BECAUSE_YOU_VIEWED => $becauseYouViewed['items'] ?? collect(),
                RecommendationEvent::SECTION_TRENDING => $trending,
                RecommendationEvent::SECTION_GOOD_DEALS => $goodDeals,
                RecommendationEvent::SECTION_JUST_LISTED => $justListed,
            ]);
        }

        return view('livewire.marketplace.listing-index', [
            'listings' => $listings,
            'categories' => $categories,
            'hasActiveFilters' => $hasActiveFilters,
            'hotDeals' => $hasActiveFilters ? collect() : $this->safely(fn () => Listing::active()->hotDeals()->with(['seller:id,name,is_verified,avatar_path', 'category:id,name'])->latest('price_dropped_at')->limit(8)->get(), collect()),
            'popularWithYou' => ($hasActiveFilters || ! Auth::check()) ? collect() : $this->safely(fn () => app(BuyerInsightsService::class)->popularListings($user), collect()),
            'recommendedForYou' => $recommendedForYou,
            'becauseYouViewed' => $becauseYouViewed,
            'trending' => $trending,
            'goodDeals' => $goodDeals,
            'justListed' => $justListed,
            'canSell' => Auth::check() ? Gate::allows('create', Listing::class) : null,
        ])->layout('layouts.app', ['title' => 'Campus Marketplace - UniMarket']);
    }

    /**
     * @param  array<string, Collection<int, Listing>>  $sections
     */
    private function logImpressionsOnce(array $sections): void
    {
        if ($this->recommendationImpressionsLogged) {
            return;
        }

        $this->recommendationImpressionsLogged = true;
        $userId = Auth::id();
        $sessionHash = hash('sha256', session()->getId());
        $now = now();

        $rows = [];
        foreach ($sections as $section => $items) {
            foreach ($items as $listing) {
                $rows[] = [
                    'listing_id' => $listing->id,
                    'user_id' => $userId,
                    'session_hash' => $sessionHash,
                    'section' => $section,
                    'type' => RecommendationEvent::TYPE_IMPRESSION,
                    'created_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            RecommendationEvent::insert($rows);
        }
    }

    /**
     * Runs an optional, non-essential section of the page (a recommendation
     * carousel, hot deals, popular-with-you) and falls back instead of
     * taking the whole marketplace page down with it - a broken
     * recommendation is just a hidden carousel, never a 500.
     */
    private function safely(\Closure $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }
}
