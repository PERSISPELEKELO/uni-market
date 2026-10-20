<?php

use App\Models\AuditLog;
use App\Models\StudentVerificationDocument;
use App\Models\User;
use App\Notifications\StudentVerificationRejected;
use App\Services\StudentVerificationService;
use Illuminate\Support\Facades\Notification;

function reviewer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function submission(): StudentVerificationDocument
{
    $student = User::factory()->studentVerificationSubmitted()->create();

    return StudentVerificationDocument::factory()->for($student)->create();
}

it('approves a submission and records who reviewed it', function () {
    $document = submission();
    $admin = reviewer();

    app(StudentVerificationService::class)->approve($document, $admin);

    $fresh = $document->user->fresh();
    expect($fresh->is_verified)->toBeTrue()
        ->and($fresh->student_verification_status)->toBe(User::STUDENT_VERIFICATION_VERIFIED)
        ->and($fresh->student_verification_reviewed_by)->toBe($admin->id)
        ->and($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_APPROVED);
});

it('refuses to act on a submission the student has replaced with a newer one', function () {
    $document = submission();
    StudentVerificationDocument::factory()->for($document->user)->create();

    expect(fn () => app(StudentVerificationService::class)->approve($document, reviewer()))
        ->toThrow(InvalidArgumentException::class);

    expect($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_PENDING);
});

it('still records the decision when sending the notification fails', function () {
    Notification::shouldReceive('send')->andThrow(new RuntimeException('mail server unreachable'));

    $document = submission();

    app(StudentVerificationService::class)->approve($document, reviewer());

    expect($document->user->fresh()->is_verified)->toBeTrue();
});

it('distinguishes a hard rejection from a request to resubmit', function () {
    Notification::fake();

    $rejected = submission();
    app(StudentVerificationService::class)->reject($rejected, reviewer(), 'not_own_card');
    expect($rejected->user->fresh()->student_verification_status)->toBe(User::STUDENT_VERIFICATION_REJECTED)
        ->and($rejected->user->fresh()->student_verification_rejection_reason)->toBe(StudentVerificationDocument::REJECTION_REASONS['not_own_card']);
    Notification::assertSentTo($rejected->user, fn (StudentVerificationRejected $notification) => $notification->toArray($rejected->user)['title'] === 'Your student verification was not approved');

    $resubmit = submission();
    app(StudentVerificationService::class)->requestResubmission($resubmit, reviewer(), 'blurry_or_unclear');
    expect($resubmit->user->fresh()->student_verification_status)->toBe(User::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED);
    Notification::assertSentTo($resubmit->user, fn (StudentVerificationRejected $notification) => $notification->toArray($resubmit->user)['title'] === 'Please resubmit your student document');
});

it('lets an admin give a custom reason when none of the standard ones fit', function () {
    Notification::fake();
    $document = submission();

    app(StudentVerificationService::class)->reject($document, reviewer(), 'other', 'The photo shows a library card, not a student ID.');

    expect($document->user->fresh()->student_verification_rejection_reason)
        ->toBe('The photo shows a library card, not a student ID.');
});

it('logs only the reason code, never the free-text reason, in the audit trail', function () {
    Notification::fake();
    $document = submission();

    app(StudentVerificationService::class)->reject($document, reviewer(), 'name_mismatch');

    $log = AuditLog::where('action', 'STUDENT_VERIFICATION_REJECTED')->latest('id')->first();

    expect($log->payload['reason_code'])->toBe('name_mismatch')
        ->and($log->payload)->not->toHaveKey('reason')
        ->and(json_encode($log->payload))->not->toContain("doesn't match your registered name");
});
