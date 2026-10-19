<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\SavedListing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\BuyerInsightsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The buyer-side counterpart to the seller dashboard (My Listings) - every
 * section here is real data already in the app, nothing invented:
 * "Recent activity" and "Saved" are the student's own listing_events/
 * saved_listings rows; "Your alerts" is a saved listing whose price has
 * genuinely dropped since it was saved; "Following" is sellers this student
 * has actually completed a purchase from before (there is no generic
 * follow-a-seller/category system in the app to build this from instead).
 */
class BuyingHub extends Component
{
    private const RECENT_LIMIT = 10;

    public function render()
    {
        $user = Auth::user();

        return view('livewire.activity.buying-hub', [
            'recentActivity' => $this->recentActivity($user),
            'saved' => $this->savedListings($user),
            'alerts' => $this->priceDropAlerts($user),
            'following' => $this->following($user),
            'activeListingCount' => $user->listings()->active()->count(),
            'buyerTotals' => app(BuyerInsightsService::class)->totals($user),
        ])->layout('layouts.app', ['title' => 'Buying - UniMarket']);
    }

    /**
     * @return Collection<int, Listing>
     */
    private function recentActivity(User $user): Collection
    {
        $listingIds = ListingEvent::where('user_id', $user->id)
            ->where('type', ListingEvent::TYPE_VIEW)
            ->latest('created_at')
            ->limit(self::RECENT_LIMIT)
            ->pluck('listing_id');

        $listings = Listing::whereIn('id', $listingIds)->with('category')->get()->keyBy('id');

        // Preserve the most-recent-first order from the event log, not
        // whatever order whereIn() happens to return rows in.
        return $listingIds->unique()->map(fn (int $id) => $listings->get($id))->filter()->values();
    }

    /**
     * @return Collection<int, SavedListing>
     */
    private function savedListings(User $user): Collection
    {
        return SavedListing::where('user_id', $user->id)
            ->with('listing.category')
            ->latest('created_at')
            ->get()
            ->filter(fn (SavedListing $saved) => $saved->listing !== null)
            ->values();
    }

    /**
     * @return Collection<int, SavedListing>
     */
    private function priceDropAlerts(User $user): Collection
    {
        return $this->savedListings($user)->filter(fn (SavedListing $saved) => $saved->hasPriceDrop())->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function following(User $user): Collection
    {
        $sellerIds = Transaction::where('buyer_id', $user->id)
            ->where('status', 'COMPLETED')
            ->distinct()
            ->pluck('seller_id');

        return User::whereIn('id', $sellerIds)->get();
    }
}
