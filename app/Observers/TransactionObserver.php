<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Transaction;
use App\Notifications\TransactionCompletedRateReminder;

/**
 * Fires the "please rate" reminder to both parties the moment a transaction
 * genuinely transitions into COMPLETED, regardless of which of the two
 * existing code paths did it (buyer-confirmed acceptance or the
 * auto-complete-after-inspection-window command) - kept here once instead of
 * duplicated in both places.
 */
class TransactionObserver
{
    public function updated(Transaction $transaction): void
    {
        if (! $transaction->wasChanged('status')) {
            return;
        }

        if (strtoupper((string) $transaction->status) !== 'COMPLETED') {
            return;
        }

        $transaction->buyer->notify(new TransactionCompletedRateReminder($transaction, $transaction->seller));
        $transaction->seller->notify(new TransactionCompletedRateReminder($transaction, $transaction->buyer));
    }
}
