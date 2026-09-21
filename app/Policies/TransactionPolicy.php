<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    /**
     * Only the buyer and the seller can see a transaction and its handover details.
     */
    public function view(User $user, Transaction $transaction): Response
    {
        return $transaction->isBuyer($user) || $transaction->isSeller($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * The seller confirms the physical handover using the buyer's code.
     */
    public function verifyHandover(User $user, Transaction $transaction): Response
    {
        return $transaction->isSeller($user)
            ? Response::allow()
            : Response::deny('Only the seller can verify the handover code.');
    }

    /**
     * The buyer accepts the item while the inspection window is open.
     */
    public function complete(User $user, Transaction $transaction): Response
    {
        if (! $transaction->isBuyer($user)) {
            return Response::deny('Only the buyer can confirm completion.');
        }

        return $transaction->isInInspection()
            ? Response::allow()
            : Response::deny('You can only confirm completion after the handover has been verified.');
    }

    /**
     * The buyer can raise a dispute, during the inspection window only.
     */
    public function dispute(User $user, Transaction $transaction): Response
    {
        return $transaction->isBuyer($user)
            ? Response::allow()
            : Response::deny('Only the buyer can raise a dispute.');
    }
}
