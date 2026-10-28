<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Classifies every transaction a user takes part in (buyer or seller) into
 * the four hub tabs, and counts how many need that user's action right now
 * - the single source of truth behind the header/sidebar/mobile badge.
 */
class TransactionInboxService
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array{needs_action: Collection, in_progress: Collection, completed: Collection, disputed: Collection}
     */
    public function grouped(User $user): array
    {
        $transactions = Transaction::with(['buyer:id,name', 'seller:id,name', 'listing:id,title,images'])
            ->forParticipant($user->id)
            ->get();

        $ratedTransactionIds = Rating::where('rater_id', $user->id)
            ->whereIn('transaction_id', $transactions->pluck('id'))
            ->pluck('transaction_id');

        $rows = $transactions->map(fn (Transaction $t) => $this->classify($t, $user, $ratedTransactionIds));

        $sorted = fn (Collection $items) => $items->sortBy([
            fn ($a, $b) => ($a['inspection_ends_at'] ?? PHP_INT_MAX) <=> ($b['inspection_ends_at'] ?? PHP_INT_MAX),
            fn ($a, $b) => $a['transaction']->created_at <=> $b['transaction']->created_at,
        ])->values();

        return [
            'needs_action' => $sorted($rows->where('tab', 'needs_action')),
            'in_progress' => $sorted($rows->where('tab', 'in_progress')),
            'completed' => $rows->where('tab', 'completed')->sortByDesc(fn ($r) => $r['transaction']->completed_at)->values(),
            'disputed' => $rows->where('tab', 'disputed')->values(),
        ];
    }

    /**
     * A plain integer, cached briefly - never an object, so this is always
     * safe for any cache store. Cleared by TransactionObserver whenever a
     * transaction this user takes part in changes status, and by
     * RateTransaction after a rating is submitted.
     */
    public function needsActionCount(User $user): int
    {
        return Cache::remember(self::cacheKey($user->id), self::CACHE_TTL_SECONDS, fn () => $this->grouped($user)['needs_action']->count());
    }

    public static function forgetCountFor(int $userId): void
    {
        Cache::forget(self::cacheKey($userId));
    }

    private static function cacheKey(int $userId): string
    {
        return "transactions:needs-action-count:{$userId}";
    }

    /**
     * @return array{transaction: Transaction, role: string, tab: string, action_label: ?string, status_label: string, step: int, inspection_ends_at: ?Carbon}
     */
    private function classify(Transaction $transaction, User $user, Collection $ratedTransactionIds): array
    {
        $status = strtoupper((string) $transaction->status);
        $role = $transaction->isBuyer($user) ? 'buying' : 'selling';
        $inspectionEnd = $transaction->inspection_expires_at ?? $transaction->inspection_ends_at;

        if (in_array($status, ['DISPUTED', 'REFUNDED'], true)) {
            return $this->row($transaction, $role, 'disputed', null, $status === 'DISPUTED' ? 'Disputed' : 'Refunded', 4, $inspectionEnd);
        }

        if ($status === 'COMPLETED') {
            if (! $ratedTransactionIds->contains($transaction->id)) {
                return $this->row($transaction, $role, 'needs_action', 'Rate the other person', 'Completed', 4, $inspectionEnd);
            }

            return $this->row($transaction, $role, 'completed', null, 'Completed', 4, $inspectionEnd);
        }

        if ($transaction->isDirectMode()) {
            return $role === 'buying'
                ? $this->row($transaction, $role, 'needs_action', 'Complete purchase', 'Awaiting your confirmation', 2, $inspectionEnd)
                : $this->row($transaction, $role, 'in_progress', null, 'Waiting for the buyer to confirm', 2, $inspectionEnd);
        }

        if (in_array($status, ['ITEM_INSPECTION', 'HANDED_OVER'], true)) {
            return $role === 'buying'
                ? $this->row($transaction, $role, 'needs_action', 'Confirm item or raise a dispute', 'Inspection period - your turn', 3, $inspectionEnd)
                : $this->row($transaction, $role, 'in_progress', null, 'Inspection period running', 3, $inspectionEnd);
        }

        // INITIATED, RESERVED, PENDING, PENDING_MEETING: waiting to meet up.
        if ($role === 'selling') {
            return $this->row($transaction, $role, 'needs_action', 'Enter handover code', 'Waiting for you to confirm the handover', 2, $inspectionEnd);
        }

        $hasCode = (bool) ($transaction->handover_otp_plain ?: $transaction->handover_code_plain);

        return $hasCode
            ? $this->row($transaction, $role, 'needs_action', 'Show your code to the seller', 'Meeting the seller', 2, $inspectionEnd)
            : $this->row($transaction, $role, 'in_progress', null, 'Waiting to meet the seller', 1, $inspectionEnd);
    }

    /**
     * @return array{transaction: Transaction, role: string, tab: string, action_label: ?string, status_label: string, step: int, inspection_ends_at: ?Carbon}
     */
    private function row(Transaction $transaction, string $role, string $tab, ?string $actionLabel, string $statusLabel, int $step, $inspectionEnd): array
    {
        return [
            'transaction' => $transaction,
            'role' => $role,
            'tab' => $tab,
            'action_label' => $actionLabel,
            'status_label' => $statusLabel,
            'step' => $step,
            'inspection_ends_at' => $inspectionEnd,
        ];
    }
}
