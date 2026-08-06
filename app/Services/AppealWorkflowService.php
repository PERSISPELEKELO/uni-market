<?php

namespace App\Services;

use App\Models\Appeal;
use App\Models\User;
use DomainException;
use InvalidArgumentException;

class AppealWorkflowService
{
    public function __construct(
        protected AuditLoggerService $auditLogger
    ) {}

    /**
     * Submit a new appeal by a student/user.
     */
    public function submitAppeal(
        User $user,
        string $targetType,
        string|int $targetId,
        string $reason,
        ?array $evidenceUrls = []
    ): Appeal {
        if (empty(trim($reason))) {
            throw new InvalidArgumentException('Appeal reason cannot be empty.');
        }

        $appeal = Appeal::create([
            'user_id' => $user->id,
            'target_type' => $targetType,
            'target_id' => (string) $targetId,
            'reason' => $reason,
            'evidence_urls' => $evidenceUrls ?? [],
            'status' => Appeal::STATUS_PENDING,
        ]);

        $this->auditLogger->log(
            'APPEAL_SUBMITTED',
            'Appeal',
            $appeal->id,
            [
                'user_id' => $user->id,
                'target_type' => $targetType,
                'target_id' => (string) $targetId,
                'reason' => $reason,
            ],
            $user
        );

        return $appeal;
    }

    /**
     * Transition an appeal from PENDING to UNDER_REVIEW.
     */
    public function startReview(Appeal $appeal, User $actor): Appeal
    {
        if ($appeal->status !== Appeal::STATUS_PENDING) {
            throw new DomainException("Cannot start review for an appeal with status '{$appeal->status}'. Expected 'PENDING'.");
        }

        $appeal->update([
            'status' => Appeal::STATUS_UNDER_REVIEW,
        ]);

        $this->auditLogger->log(
            'APPEAL_REVIEW_STARTED',
            'Appeal',
            $appeal->id,
            [
                'previous_status' => Appeal::STATUS_PENDING,
                'new_status' => Appeal::STATUS_UNDER_REVIEW,
            ],
            $actor
        );

        return $appeal;
    }

    /**
     * Resolve an appeal with status UPHELD or OVERTURNED.
     */
    public function decideAppeal(
        Appeal $appeal,
        string $outcome,
        ?string $governanceNotes,
        User $actor
    ): Appeal {
        if ($appeal->status !== Appeal::STATUS_UNDER_REVIEW) {
            throw new DomainException("Cannot decide an appeal with status '{$appeal->status}'. Expected 'UNDER_REVIEW'.");
        }

        if (!in_array($outcome, [Appeal::STATUS_UPHELD, Appeal::STATUS_OVERTURNED], true)) {
            throw new InvalidArgumentException("Invalid resolution outcome '{$outcome}'. Must be UPHELD or OVERTURNED.");
        }

        $appeal->update([
            'status' => $outcome,
            'governance_notes' => $governanceNotes,
            'resolved_at' => now(),
        ]);

        $this->auditLogger->log(
            'APPEAL_DECIDED',
            'Appeal',
            $appeal->id,
            [
                'outcome' => $outcome,
                'governance_notes' => $governanceNotes,
                'resolved_at' => $appeal->resolved_at->toIso8601String(),
            ],
            $actor
        );

        return $appeal;
    }
}
