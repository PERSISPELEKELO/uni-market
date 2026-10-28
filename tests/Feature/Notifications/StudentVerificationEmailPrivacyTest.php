<?php

use App\Models\StudentVerificationDocument;
use App\Models\User;
use App\Notifications\NewStudentVerificationSubmission;
use App\Notifications\StudentVerificationApproved;
use App\Notifications\StudentVerificationRejected;
use App\Notifications\StudentVerificationReminder;
use App\Notifications\StudentVerificationSubmitted;

/**
 * Every student-verification email must be safe to forward or read over
 * someone's shoulder: never the student's full student number, and never a
 * password. This renders each notification's actual mail body rather than
 * inspecting source code, so it catches the real output.
 */
it('never puts the student\'s full student number in any verification email', function () {
    $user = User::factory()->create(['student_id' => '2048193']);
    $document = StudentVerificationDocument::factory()->for($user)->create();
    $staff = User::factory()->create(['role' => 'admin']);

    $bodies = [
        (new StudentVerificationApproved)->toMail($user)->render(),
        (new StudentVerificationRejected(true, 'The photo was too blurry to read.'))->toMail($user)->render(),
        (new StudentVerificationSubmitted)->toMail($user)->render(),
        (new StudentVerificationReminder(StudentVerificationReminder::STAGE_24_HOURS))->toMail($user)->render(),
        (new StudentVerificationReminder(StudentVerificationReminder::STAGE_3_DAYS))->toMail($user)->render(),
        (new NewStudentVerificationSubmission($document))->toMail($staff)->render(),
    ];

    foreach ($bodies as $body) {
        expect((string) $body)->not->toContain('2048193');
    }
});

it('never puts a password in any verification email', function () {
    $user = User::factory()->create(['student_id' => '2048194']);
    $document = StudentVerificationDocument::factory()->for($user)->create();
    $staff = User::factory()->create(['role' => 'admin']);

    $bodies = [
        (new StudentVerificationApproved)->toMail($user)->render(),
        (new StudentVerificationRejected(false, 'Not a valid student ID.'))->toMail($user)->render(),
        (new StudentVerificationSubmitted)->toMail($user)->render(),
        (new StudentVerificationReminder(StudentVerificationReminder::STAGE_24_HOURS))->toMail($user)->render(),
        (new NewStudentVerificationSubmission($document))->toMail($staff)->render(),
    ];

    foreach ($bodies as $body) {
        expect((string) $body)->not->toContain($user->password);
    }
});
