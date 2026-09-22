<?php

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
    app(StudentVerificationService::class)->reject($rejected, reviewer(), 'Not a valid student ID.');
    expect($rejected->user->fresh()->student_verification_status)->toBe(User::STUDENT_VERIFICATION_REJECTED);
    Notification::assertSentTo($rejected->user, fn (StudentVerificationRejected $notification) => $notification->toArray($rejected->user)['title'] === 'Your student verification was not approved');

    $resubmit = submission();
    app(StudentVerificationService::class)->requestResubmission($resubmit, reviewer(), 'Please upload a sharper photo.');
    expect($resubmit->user->fresh()->student_verification_status)->toBe(User::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED);
    Notification::assertSentTo($resubmit->user, fn (StudentVerificationRejected $notification) => $notification->toArray($resubmit->user)['title'] === 'Please resubmit your student document');
});
