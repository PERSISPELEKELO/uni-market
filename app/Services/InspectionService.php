<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DisputeWindowExpiredException;
use App\Models\Dispute;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use InvalidArgumentException;

class InspectionService
{
    public function __construct(
        protected AuditLoggerService $auditLogger,
        protected DisputeAnalysisService $disputeAnalysis
    ) {}

    /**
     * Check if buyer can raise a dispute during the post-purchase inspection window.
     */
    public function canRaiseDispute(Transaction $transaction, User $buyer): bool
    {
        if ($buyer->id !== $transaction->buyer_id) {
            return false;
        }

        $status = strtoupper($transaction->status);
        if (! in_array($status, ['ITEM_INSPECTION', 'HANDED_OVER'], true)) {
            return false;
        }

        $expiration = $transaction->inspection_expires_at ?? $transaction->inspection_ends_at;
        if (! $expiration) {
            return false;
        }

        return now()->lessThanOrEqualTo($expiration);
    }

    /**
     * Raise a post-purchase dispute during the active inspection window.
     */
    public function raisePostPurchaseDispute(Transaction $transaction, User $buyer, array $disputeData): Dispute
    {
        if ($buyer->id !== $transaction->buyer_id) {
            throw new InvalidArgumentException('Only the buyer can raise a post-purchase dispute.');
        }

        if (! $this->canRaiseDispute($transaction, $buyer)) {
            $hours = $transaction->inspection_duration_hours ?? $transaction->inspection_period_hours ?? 48;
            throw new DisputeWindowExpiredException("Inspection period of {$hours} hours has lapsed. Disputes can no longer be raised automatically.");
        }

        $reason = $disputeData['reason'] ?? 'Defective or non-matching item during inspection window.';

        // Ask the AI microservice unless the caller already supplied an analysis. Advisory only: failures return nulls.
        if (! array_key_exists('ai_sentiment_score', $disputeData)) {
            $disputeData += $this->disputeAnalysis->analyze($transaction, $reason);
        }

        $transaction->update([
            'status' => 'DISPUTED',
        ]);

        $dispute = Dispute::create([
            'transaction_id' => $transaction->id,
            'raised_by' => $buyer->id,
            'reason' => $reason,
            'status' => 'open',
            'ai_sentiment_score' => $disputeData['ai_sentiment_score'] ?? null,
            'ai_confidence_score' => $disputeData['ai_confidence_score'] ?? null,
            'ai_suggested_resolution' => $disputeData['ai_suggested_resolution'] ?? null,
            'ai_analysis_summary' => $disputeData['ai_analysis_summary'] ?? null,
        ]);

        $this->auditLogger->recordAction(
            $buyer,
            'POST_PURCHASE_DISPUTE_RAISED',
            'Transaction',
            (string) $transaction->id,
            [
                'dispute_id' => $dispute->id,
                'reason' => $dispute->reason,
                'evidence_urls' => $disputeData['evidence_urls'] ?? [],
            ]
        );

        return $dispute;
    }

    /**
     * Confirm early item acceptance by buyer, finalizing the transaction immediately.
     */
    public function confirmItemAcceptance(Transaction $transaction, User $buyer): void
    {
        if ($buyer->id !== $transaction->buyer_id) {
            throw new InvalidArgumentException('Only the buyer can confirm item acceptance.');
        }

        $status = strtoupper($transaction->status);
        if (! in_array($status, ['ITEM_INSPECTION', 'HANDED_OVER'], true)) {
            throw new DomainException("Cannot accept item for a transaction with status '{$transaction->status}'. Expected 'ITEM_INSPECTION'.");
        }

        $completedAt = now();

        $transaction->update([
            'status' => 'COMPLETED',
            'completed_at' => $completedAt,
        ]);

        if ($transaction->listing) {
            $transaction->listing->update(['status' => 'sold']);
        }

        $this->auditLogger->recordAction(
            $buyer,
            'TRANSACTION_BUYER_ACCEPTED',
            'Transaction',
            (string) $transaction->id,
            [
                'completed_at' => $completedAt->toIso8601String(),
            ]
        );
    }
}
