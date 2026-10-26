<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Transaction;
use App\Notifications\TransactionCompletedRateReminder;
use App\Services\Insights\InsightsCache;
use App\Services\TransactionInboxService;

/**
 * Fires the "please rate" reminder to both parties the moment a transaction
 * genuinely transitions into COMPLETED, regardless of which of the two
 * existing code paths did it (buyer-confirmed acceptance or the
 * auto-complete-after-inspection-window command) - kept here once instead of
 * duplicated in both places. Also invalidates every Insights cache, since a
 * newly completed sale changes almost every BI aggregate.
 */
class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $this->forgetInboxCounts($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        if (! $transaction->wasChanged('status')) {
            return;
        }

        $this->forgetInboxCounts($transaction);

        if (strtoupper((string) $transaction->status) !== 'COMPLETED') {
            return;
        }

        InsightsCache::bump();

        $transaction->buyer->notify(new TransactionCompletedRateReminder($transaction, $transaction->seller));
        $transaction->seller->notify(new TransactionCompletedRateReminder($transaction, $transaction->buyer));
    }

    /**
     * A status change can move either party in or out of "needs your
     * action", so both of their cached counts must be invalidated.
     */
    private function forgetInboxCounts(Transaction $transaction): void
    {
        TransactionInboxService::forgetCountFor($transaction->buyer_id);
        TransactionInboxService::forgetCountFor($transaction->seller_id);
    }
}
