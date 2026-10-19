<?php

namespace App\Livewire\Marketplace;

use App\Models\Category;
use App\Models\Listing;
use App\Models\RecommendationEvent;
use App\Models\SearchLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\BuyerInsightsService;
use App\Services\Recommendations\PersonalizedFeedService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class ListingIndex extends Component
{
    use WithPagination;

    private const SORTS = ['latest', 'price_asc', 'price_desc'];

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

    /**
     * How many of the busiest categories show as chips before the rest
     * fold into "More" - keeps the row to one scrollable line instead of
     * wrapping across several.
     */
    private const TOP_CATEGORY_COUNT = 6;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategory' => ['except' => null, 'as' => 'category'],
        'conditionFilter' => ['except' => '', 'as' => 'condition'],
        'sortBy' => ['except' => 'latest'],
        'hotDealsOnly' => ['except' => false, 'as' => 'deals'],
        'priceMin' => ['except' => '', 'as' => 'min'],
        'priceMax' => ['except' => '', 'as' => 'max'],
    ];

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
            ->paginate(12);

        $categories = Category::withCount(['listings' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->get();

        $hasActiveFilters = $this->search !== '' || $this->selectedCategory !== null || $condition !== '' || $sortBy !== 'latest' || $this->hotDealsOnly || $priceMin !== null || $priceMax !== null;

        $feed = app(PersonalizedFeedService::class);
        $user = Auth::user();

        // Carousels are only shown on the unfiltered homepage - once someone
        // is filtering, the main grid below already shows matching results.
        $recommendedForYou = $hasActiveFilters ? collect() : $feed->recommendedForYou($user);
        $becauseYouViewed = $hasActiveFilters ? null : $feed->becauseYouViewed($user);
        $trending = $hasActiveFilters ? collect() : $feed->trending();
        $goodDeals = $hasActiveFilters ? collect() : $feed->goodDeals();
        $justListed = $hasActiveFilters ? collect() : $feed->justListed();

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
            'topCategories' => $this->topCategories($categories),
            'hasActiveFilters' => $hasActiveFilters,
            'hotDeals' => $hasActiveFilters ? collect() : Listing::active()->hotDeals()->with(['seller:id,name,is_verified,avatar_path', 'category:id,name'])->latest('price_dropped_at')->limit(8)->get(),
            'popularWithYou' => ($hasActiveFilters || ! Auth::check()) ? collect() : app(BuyerInsightsService::class)->popularListings($user),
            'recommendedForYou' => $recommendedForYou,
            'becauseYouViewed' => $becauseYouViewed,
            'trending' => $trending,
            'goodDeals' => $goodDeals,
            'justListed' => $justListed,
            'heroStats' => Auth::check() ? null : $this->heroStats(),
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
     * The busiest categories (by active listing count), capped so the chip
     * row stays to one scrollable line - everything else is reachable via
     * the "More" chip instead of wrapping across several rows. Falls back
     * to a fixed, sensible order when there's no listing data yet at all
     * (a fresh install), rather than an arbitrary/alphabetical one.
     *
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function topCategories(Collection $categories): Collection
    {
        if ($categories->sum('listings_count') === 0) {
            $fallbackOrder = [
                'electronics-laptops', 'books-textbooks', 'clothing-fashion',
                'phones-accessories', 'furniture', 'food-beverages',
            ];

            return collect($fallbackOrder)
                ->map(fn (string $slug) => $categories->firstWhere('slug', $slug))
                ->filter()
                ->values();
        }

        return $categories->sortByDesc('listings_count')->take(self::TOP_CATEGORY_COUNT)->values();
    }

    /**
     * Real headline numbers for the guest-facing hero, cached briefly since
     * they change slowly and every guest page load would otherwise run
     * three counts. Never a placeholder/invented figure.
     *
     * @return array{students: int, listings: int, sales: int}
     */
    private function heroStats(): array
    {
        return Cache::remember('marketplace:hero-stats', now()->addMinutes(10), fn () => [
            'students' => User::where('role', 'student')->count(),
            'listings' => Listing::active()->count(),
            'sales' => Transaction::where('status', 'COMPLETED')->count(),
        ]);
    }
}
