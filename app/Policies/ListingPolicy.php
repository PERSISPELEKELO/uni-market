<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ListingPolicy
{
    /**
     * Anyone may view a listing, except suspended or draft ones, which only
     * the seller and staff can see.
     */
    public function view(?User $user, Listing $listing): bool
    {
        if (! in_array($listing->status, [Listing::STATUS_SUSPENDED, Listing::STATUS_DRAFT], true)) {
            return true;
        }

        return $user !== null && ($listing->isOwnedBy($user) || $user->isAdmin() || $user->isGovernanceCommittee());
    }

    /**
     * Only a student whose ID has been approved ("Verified Student") may
     * sell - enforced here, not just by hiding the Sell button, so a direct
     * request to create a listing is blocked the same way everywhere
     * (CreateListing's render() uses this same check to decide whether to
     * show the form or a "get verified" screen).
     */
    public function create(User $user): Response
    {
        if (! $user->hasVerifiedEmail()) {
            return Response::deny('Please verify your email address before selling.');
        }

        if (! $user->isStudentVerified()) {
            return Response::deny('Only verified ZUT students can sell. Upload your student ID to get verified.');
        }

        return Response::allow();
    }

    /**
     * Only the seller can edit, and only while the item is still a draft or
     * open for sale.
     */
    public function update(User $user, Listing $listing): Response
    {
        if (! $listing->isOwnedBy($user)) {
            return Response::denyAsNotFound();
        }

        if (! in_array($listing->status, [Listing::STATUS_ACTIVE, Listing::STATUS_DRAFT], true)) {
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
