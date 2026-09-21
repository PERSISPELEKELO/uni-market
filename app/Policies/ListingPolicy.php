<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ListingPolicy
{
    /**
     * Anyone may view a listing, except suspended ones which only the seller and staff can see.
     */
    public function view(?User $user, Listing $listing): bool
    {
        if ($listing->status !== Listing::STATUS_SUSPENDED) {
            return true;
        }

        return $user !== null && ($listing->isOwnedBy($user) || $user->isAdmin() || $user->isGovernanceCommittee());
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the seller can edit, and only while the item is still open for sale.
     */
    public function update(User $user, Listing $listing): Response
    {
        if (! $listing->isOwnedBy($user)) {
            return Response::denyAsNotFound();
        }

        if ($listing->status !== Listing::STATUS_ACTIVE) {
            return Response::deny('This listing is reserved, sold or suspended, so it can no longer be edited.');
        }

        return Response::allow();
    }

    /**
     * Only the seller can remove a listing, and never while a buyer has reserved it.
     */
    public function delete(User $user, Listing $listing): Response
    {
        if (! $listing->isOwnedBy($user)) {
            return Response::denyAsNotFound();
        }

        if ($listing->status === Listing::STATUS_PENDING) {
            return Response::deny('A buyer has reserved this item. Finish or cancel the transaction before removing the listing.');
        }

        return Response::allow();
    }
}
