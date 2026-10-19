<?php

namespace App\Livewire\Marketplace;

use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\Message;
use App\Models\Reservation;
use App\Services\AuditLoggerService;
use App\Services\Recommendations\ContentSimilarityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MyListings extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public const FILTERS = ['needs_attention', 'active', 'sold', 'draft', 'to_renew'];

    public string $statusFilter = '';

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->statusFilter === $filter ? '' : $filter;
        $this->resetPage();
    }

    public function delete(int $listingId): void
    {
        $listing = $this->ownListing($listingId);

        $this->authorize('delete', $listing);

        $listing->delete();

        app(AuditLoggerService::class)->log('LISTING_DELETED', 'Listing', $listing->id, ['title' => $listing->title], Auth::user());

        $this->dispatch('notify', type: 'success', message: 'Your listing has been removed.');
    }

    public function publish(int $listingId): void
    {
        $listing = $this->ownListing($listingId);

        if ($listing->status !== Listing::STATUS_DRAFT) {
            return;
        }

        $listing->update(['status' => Listing::STATUS_ACTIVE]);

        app(AuditLoggerService::class)->log('LISTING_PUBLISHED', 'Listing', $listing->id, ['title' => $listing->title], Auth::user());
        app(ContentSimilarityService::class)->refreshFor($listing);

        $this->dispatch('notify', type: 'success', message: 'Your listing is now live.');
    }

    public function renew(int $listingId): void
    {
        $listing = $this->ownListing($listingId);

        if ($listing->status !== Listing::STATUS_ACTIVE) {
            return;
        }

        $listing->update(['renewed_at' => now()]);

        $this->dispatch('notify', type: 'success', message: 'Listing renewed.');
    }

    /**
     * A manual "sold outside UniMarket" shortcut, for a sale that never went
     * through the app's own reservation/handover flow - it only applies
     * while the listing is still plain "active" (no reservation has been
     * selected yet, which would already have moved it to "pending" through
     * the real transaction flow and its own rating/record-keeping).
     */
    public function markSoldManually(int $listingId): void
    {
        $listing = $this->ownListing($listingId);

        if ($listing->status !== Listing::STATUS_ACTIVE) {
            return;
        }

        $listing->activeReservations()->update(['status' => Reservation::STATUS_CANCELLED]);
        $listing->update(['status' => Listing::STATUS_SOLD]);

        app(AuditLoggerService::class)->log('LISTING_MARKED_SOLD', 'Listing', $listing->id, ['title' => $listing->title], Auth::user());

        $this->dispatch('notify', type: 'success', message: 'Marked as sold.');
    }

    /**
     * The client fires the real OS/browser share sheet (or copies the link);
     * this just records that a share happened, for the listing_events-backed
     * "Listing shares" KPI.
     */
    public function shareListing(int $listingId): void
    {
        $listing = $this->ownListing($listingId);

        ListingEvent::create([
            'listing_id' => $listing->id,
            'user_id' => Auth::id(),
            'type' => ListingEvent::TYPE_SHARE,
            'created_at' => now(),
        ]);
    }

    private function ownListing(int $listingId): Listing
    {
        return Listing::where('user_id', Auth::id())->findOrFail($listingId);
    }

    public function render()
    {
        $sellerId = Auth::id();

        $counts = [
            'needs_attention' => Listing::where('user_id', $sellerId)->where('status', Listing::STATUS_SUSPENDED)->count(),
            'active' => Listing::where('user_id', $sellerId)->whereIn('status', [Listing::STATUS_ACTIVE, Listing::STATUS_PENDING])->count(),
            'sold' => Listing::where('user_id', $sellerId)->where('status', Listing::STATUS_SOLD)->count(),
            'draft' => Listing::where('user_id', $sellerId)->where('status', Listing::STATUS_DRAFT)->count(),
            'to_renew' => Listing::where('user_id', $sellerId)->toRenew()->count(),
        ];

        $listings = Listing::where('user_id', $sellerId)
            ->with('category')
            ->when($this->statusFilter === 'needs_attention', fn (Builder $q) => $q->where('status', Listing::STATUS_SUSPENDED))
            ->when($this->statusFilter === 'active', fn (Builder $q) => $q->whereIn('status', [Listing::STATUS_ACTIVE, Listing::STATUS_PENDING]))
            ->when($this->statusFilter === 'sold', fn (Builder $q) => $q->where('status', Listing::STATUS_SOLD))
            ->when($this->statusFilter === 'draft', fn (Builder $q) => $q->where('status', Listing::STATUS_DRAFT))
            ->when($this->statusFilter === 'to_renew', fn (Builder $q) => $q->toRenew())
            ->latest()
            ->paginate(8);

        $chatsToAnswer = Message::where('receiver_id', $sellerId)->where('is_read', false)->count();

        $needsActionListing = Listing::where('user_id', $sellerId)->where('status', Listing::STATUS_SUSPENDED)->latest()->first()
            ?? Listing::where('user_id', $sellerId)->toRenew()->oldest('created_at')->first();

        return view('livewire.marketplace.my-listings', [
            'listings' => $listings,
            'counts' => $counts,
            'chatsToAnswer' => $chatsToAnswer,
            'sellerRating' => Auth::user()->averageRating(),
            'needsActionListing' => $needsActionListing,
        ])->layout('layouts.app', ['title' => 'My Listings - UniMarket']);
    }
}
