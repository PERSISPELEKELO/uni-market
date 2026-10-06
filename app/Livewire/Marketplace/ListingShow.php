<?php

namespace App\Livewire\Marketplace;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingView;
use App\Models\Reservation;
use App\Services\Insights\FrequentlyBoughtTogetherService;
use App\Services\ReservationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

class ListingShow extends Component
{
    public Listing $listing;

    public int $activeImageIndex = 0;

    public function mount(Listing $listing): void
    {
        abort_unless(Gate::allows('view', $listing), 404);

        $this->listing = $listing->load(['seller', 'category']);

        $this->recordView();
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

        return view('livewire.marketplace.listing-show', [
            'reservationCount' => $this->listing->reservations()->active()->count(),
            'hasReserved' => $this->listing->hasActiveReservationFrom(Auth::user()),
            'activeReservations' => $activeReservations,
            'recentViewCount' => $this->listing->views()->where('viewed_at', '>=', now()->subDays(7))->count(),
            'frequentlyBoughtWith' => $this->frequentlyBoughtWith(),
        ])->layout('layouts.app', ['title' => $this->listing->title.' - UniMarket']);
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
