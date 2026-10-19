<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Listing;
use App\Notifications\SavedListingSold;
use Illuminate\Support\Facades\Notification;

/**
 * Fires the "a listing you saved has sold" notification the moment a
 * listing genuinely transitions into "sold", regardless of which of the
 * existing code paths did it (buyer-confirmed acceptance, the
 * auto-complete-after-inspection-window command, a direct-mode purchase, or
 * a seller's manual "sold outside UniMarket") - kept here once instead of
 * duplicated in all four places.
 */
class ListingObserver
{
    public function updated(Listing $listing): void
    {
        if (! $listing->wasChanged('status') || $listing->status !== Listing::STATUS_SOLD) {
            return;
        }

        $buyerId = $listing->transactions()->where('status', 'COMPLETED')->latest('completed_at')->value('buyer_id');

        $savers = $listing->savedBy()
            ->with('user')
            ->when($buyerId, fn ($query, $id) => $query->where('user_id', '!=', $id))
            ->get()
            ->pluck('user')
            ->filter();

        if ($savers->isEmpty()) {
            return;
        }

        Notification::send($savers, new SavedListingSold($listing));
    }
}
