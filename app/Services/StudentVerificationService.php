<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StudentVerificationDocument;
use App\Models\User;
use App\Notifications\StudentVerificationApproved;
use App\Notifications\StudentVerificationRejected;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Applies an administrator's decision on a submitted student ID document to
 * both the document itself and the student's overall verification status.
 * Used by the Filament admin resource; kept as a plain service (rather than
 * logic embedded in the resource) so it is reusable and directly testable,
 * matching HandoverVerificationService / InspectionService in this app.
 */
class StudentVerificationService
{
    public function __construct(
        protected AuditLoggerService $auditLogger
    ) {}

    public function approve(StudentVerificationDocument $document, User $reviewer): void
    {
        $this->apply($document, $reviewer, StudentVerificationDocument::STATUS_APPROVED, User::STUDENT_VERIFICATION_VERIFIED);
    }

    public function reject(StudentVerificationDocument $document, User $reviewer, string $reason): void
    {
        $this->apply($document, $reviewer, StudentVerificationDocument::STATUS_REJECTED, User::STUDENT_VERIFICATION_REJECTED, $reason);
    }

    public function requestResubmission(StudentVerificationDocument $document, User $reviewer, string $reason): void
    {
        $this->apply($document, $reviewer, StudentVerificationDocument::STATUS_REJECTED, User::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED, $reason);
    }

    /**
     * @throws InvalidArgumentException if the student has submitted a newer document since this one was loaded
     */
    private function apply(
        StudentVerificationDocument $document,
        User $reviewer,
        string $documentStatus,
        string $userStatus,
        ?string $reason = null
    ): void {
        $document->loadMissing('user');
        $latest = $document->user->verificationDocuments()->latest('id')->first();

        if (! $latest || $latest->id !== $document->id) {
            throw new InvalidArgumentException("This is no longer {$document->user->name}'s current submission. They have submitted a newer document.");
        }

        $approved = $userStatus === User::STUDENT_VERIFICATION_VERIFIED;

        DB::transaction(function () use ($document, $documentStatus, $userStatus, $reason, $reviewer, $approved): void {
            $document->update([
                'status' => $documentStatus,
                'admin_notes' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
            ]);

            $document->user->forceFill([
                'is_verified' => $approved,
                'student_verification_status' => $userStatus,
                'student_verification_reviewed_at' => now(),
                'student_verification_reviewed_by' => $reviewer->id,
                'student_verification_rejection_reason' => $approved ? null : $reason,
            ])->save();
        });

        $this->auditLogger->recordAction(
            $reviewer,
            $approved ? 'STUDENT_VERIFICATION_APPROVED' : 'STUDENT_VERIFICATION_REJECTED',
            'User',
            (string) $document->user_id,
            ['document_id' => $document->id, 'outcome' => $userStatus, 'reason' => $reason]
        );

        // A notification delivery problem (e.g. mail server down) must never make the
        // admin think the decision itself failed to save - it has already been committed above.
        try {
            $document->user->notify($approved
                ? new StudentVerificationApproved
                : new StudentVerificationRejected($userStatus === User::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED, $reason));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
