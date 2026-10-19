<?php

namespace App\Livewire\Marketplace;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\ListingView;
use App\Models\RecommendationEvent;
use App\Models\Reservation;
use App\Services\Insights\FrequentlyBoughtTogetherService;
use App\Services\Recommendations\CoOccurrenceService;
use App\Services\ReservationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

class ListingShow extends Component
{
    private const RECOMMENDATION_SECTIONS = [
        RecommendationEvent::SECTION_RECOMMENDED_FOR_YOU,
        RecommendationEvent::SECTION_BECAUSE_YOU_VIEWED,
        RecommendationEvent::SECTION_TRENDING,
        RecommendationEvent::SECTION_GOOD_DEALS,
        RecommendationEvent::SECTION_JUST_LISTED,
        RecommendationEvent::SECTION_SIMILAR_ITEMS,
        RecommendationEvent::SECTION_STUDENTS_ALSO_VIEWED,
    ];

    public Listing $listing;

    public int $activeImageIndex = 0;

    public bool $recommendationImpressionsLogged = false;

    public function mount(Listing $listing): void
    {
        abort_unless(Gate::allows('view', $listing), 404);

        $this->listing = $listing->load(['seller', 'category']);

        $this->recordView();
        $this->recordRecommendationClick();
    }

    /**
     * Attributes a click back to whichever recommendation carousel it came
     * from, via the ?rec= tag x-listing-card adds to a recommended item's
     * link (see RecommendationEvent) - an ordinary browse-grid click has
     * no tag and logs nothing here.
     */
    private function recordRecommendationClick(): void
    {
        $section = request()->query('rec');

        if (! is_string($section) || ! in_array($section, self::RECOMMENDATION_SECTIONS, true)) {
            return;
        }

        RecommendationEvent::create([
            'listing_id' => $this->listing->id,
            'user_id' => Auth::id(),
            'session_hash' => hash('sha256', session()->getId()),
            'section' => $section,
            'type' => RecommendationEvent::TYPE_CLICK,
            'created_at' => now(),
        ]);
    }

    /**
     * At most one row per viewer (or, for a guest, per session) per listing
     * per 24 hours - never the seller viewing their own listing. Feeds
     * SellerInsightsService's per-listing view counts and "trending" on the
     * marketplace home.
     */
    private function recordView(): void
    {
        if ($this->listing->isOwnedBy(Auth::user())) {
            return;
        }

        $viewerId = Auth::id();
        $sessionHash = hash('sha256', session()->getId());

        $alreadyCounted = ListingView::where('listing_id', $this->listing->id)
            ->where('viewed_at', '>=', now()->subHours(24))
            ->when(
                $viewerId,
                fn ($query) => $query->where('viewer_id', $viewerId),
                fn ($query) => $query->where('session_hash', $sessionHash)
            )
            ->exists();

        if ($alreadyCounted) {
            return;
        }

        ListingView::create([
            'listing_id' => $this->listing->id,
            'viewer_id' => $viewerId,
            'session_hash' => $sessionHash,
            'viewed_at' => now(),
        ]);

        ListingEvent::create([
            'listing_id' => $this->listing->id,
            'user_id' => $viewerId,
            'session_hash' => $sessionHash,
            'type' => ListingEvent::TYPE_VIEW,
            'created_at' => now(),
        ]);
    }

    public function setActiveImage(int $index): void
    {
        $this->activeImageIndex = max(0, min($index, count($this->listing->image_urls) - 1));
    }

    public function reserve(ReservationService $reservations)
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'))
                ->with('status', 'Please log in to reserve this item.');
        }

        try {
            $reservations->reserve($this->listing, Auth::user());
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());

            return null;
        }

        $this->listing->refresh();
        $this->dispatch('notify', type: 'success', message: "You're on the interest list! The seller will reach out if they choose you.");

        return null;
    }

    /**
     * Seller action: choose one of the people who reserved this listing to sell to.
     */
    public function selectBuyer(int $reservationId, ReservationService $reservations)
    {
        if (! $this->listing->isOwnedBy(Auth::user())) {
            abort(403);
        }

        $reservation = Reservation::findOrFail($reservationId);

        try {
            $transaction = $reservations->selectBuyer($reservation, Auth::user());
        } catch (InvalidArgumentException $exception) {
            $this->listing->refresh();
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());

            return null;
        }

        return redirect()->route('transactions.tracker', ['transaction' => $transaction->id])
            ->with('success', 'Buyer selected! Arrange a meet-up and confirm the handover code once you receive it.');
    }

    public function contactSeller()
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'))
                ->with('status', 'Please log in to message the seller.');
        }

        if ($this->listing->isOwnedBy(Auth::user())) {
            return null;
        }

        return redirect()->route('chat.thread', [
            'receiver' => $this->listing->user_id,
            'listing' => $this->listing->id,
        ]);
    }

    public function render()
    {
        $activeReservations = $this->listing->isOwnedBy(Auth::user())
            ? $this->listing->activeReservations()->get()
            : collect();

        $similarItems = $this->safely(fn () => Listing::active()
            ->whereIn('id', $this->listing->similarities()->pluck('similar_listing_id'))
            ->with(['seller:id,name,is_verified,avatar_path', 'category:id,name'])
            ->get()
            ->sortByDesc(fn (Listing $l) => $this->listing->similarities->firstWhere('similar_listing_id', $l->id)?->score ?? 0)
            ->values(), collect());

        $studentsAlsoViewed = $this->safely(fn () => app(CoOccurrenceService::class)->alsoViewed($this->listing), collect());

        $this->logRecommendationImpressionsOnce([
            RecommendationEvent::SECTION_SIMILAR_ITEMS => $similarItems,
            RecommendationEvent::SECTION_STUDENTS_ALSO_VIEWED => $studentsAlsoViewed,
        ]);

        return view('livewire.marketplace.listing-show', [
            'reservationCount' => $this->listing->reservations()->active()->count(),
            'hasReserved' => $this->listing->hasActiveReservationFrom(Auth::user()),
            'activeReservations' => $activeReservations,
            'recentViewCount' => $this->listing->views()->where('viewed_at', '>=', now()->subDays(7))->count(),
            'frequentlyBoughtWith' => $this->safely(fn () => $this->frequentlyBoughtWith(), collect()),
            'similarItems' => $similarItems,
            'studentsAlsoViewed' => $studentsAlsoViewed,
        ])->layout('layouts.app', ['title' => $this->listing->title.' - UniMarket']);
    }

    /**
     * Runs an optional, non-essential section of the page (recommendations,
     * "frequently bought together", similar items) and falls back to an
     * empty result instead of taking the whole listing page down with it -
     * a broken insight is just a hidden section, never a 500.
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

    /**
     * @param  array<string, Collection<int, Listing>>  $sections
     */
    private function logRecommendationImpressionsOnce(array $sections): void
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
     * Categories students who bought this item's category also tend to buy
     * from, as a "browse that category too" nudge - never specific listing
     * recommendations, since the underlying service only has category-level
     * signal. Hidden below the usual privacy threshold, same as everywhere
     * else this service is used.
     *
     * @return Collection<int, array{category: Category, confidence: float}>
     */
    private function frequentlyBoughtWith(): Collection
    {
        $categoryName = $this->listing->category->name;

        return app(FrequentlyBoughtTogetherService::class)
            ->forCategory($this->listing->category->slug)
            ->map(function (array $pair) use ($categoryName) {
                $otherName = $pair['category_a'] === $categoryName ? $pair['category_b'] : $pair['category_a'];

                return ['category' => Category::where('name', $otherName)->first(), 'confidence' => $pair['confidence']];
            })
            ->filter(fn (array $row) => $row['category'] !== null)
            ->values();
    }
}
