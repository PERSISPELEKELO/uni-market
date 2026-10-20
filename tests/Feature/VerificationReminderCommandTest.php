<?php

use App\Models\User;
use App\Notifications\StudentVerificationReminder;
use Illuminate\Support\Facades\Notification;

it('reminds a student 24 hours after registering if they still haven\'t submitted', function () {
    Notification::fake();
    $user = User::factory()->create([
        'student_verification_status' => User::STUDENT_VERIFICATION_NOT_SUBMITTED,
        'created_at' => now()->subHours(25),
    ]);

    $this->artisan('verification:send-reminders')->assertExitCode(0);

    Notification::assertSentTo($user, fn (StudentVerificationReminder $n) => $n->stage === StudentVerificationReminder::STAGE_24_HOURS);
    expect($user->fresh()->student_verification_reminder_24h_sent_at)->not->toBeNull();
});

it('reminds a student again 3 days after registering if they still haven\'t submitted', function () {
    Notification::fake();
    $user = User::factory()->create([
        'student_verification_status' => User::STUDENT_VERIFICATION_NOT_SUBMITTED,
        'created_at' => now()->subDays(4),
        'student_verification_reminder_24h_sent_at' => now()->subDays(3),
    ]);

    $this->artisan('verification:send-reminders')->assertExitCode(0);

    Notification::assertSentTo($user, fn (StudentVerificationReminder $n) => $n->stage === StudentVerificationReminder::STAGE_3_DAYS);
    expect($user->fresh()->student_verification_reminder_3d_sent_at)->not->toBeNull();
});

it('never sends either reminder twice', function () {
    Notification::fake();
    $user = User::factory()->create([
        'student_verification_status' => User::STUDENT_VERIFICATION_NOT_SUBMITTED,
        'created_at' => now()->subDays(4),
        'student_verification_reminder_24h_sent_at' => now()->subDays(3),
        'student_verification_reminder_3d_sent_at' => now()->subHours(2),
    ]);

    $this->artisan('verification:send-reminders')->assertExitCode(0);

    Notification::assertNothingSentTo($user);
});

it('stops reminding once the student submits a document', function () {
    Notification::fake();
    $user = User::factory()->create([
        'student_verification_status' => User::STUDENT_VERIFICATION_PENDING,
        'created_at' => now()->subDays(4),
    ]);

    $this->artisan('verification:send-reminders')->assertExitCode(0);

    Notification::assertNothingSentTo($user);
});

it('never reminds a student who hasn\'t verified their email yet', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create([
        'student_verification_status' => User::STUDENT_VERIFICATION_NOT_SUBMITTED,
        'created_at' => now()->subDays(4),
    ]);

    $this->artisan('verification:send-reminders')->assertExitCode(0);

    Notification::assertNothingSentTo($user);
});

it('leaves a freshly-registered student alone', function () {
    Notification::fake();
    $user = User::factory()->create([
        'student_verification_status' => User::STUDENT_VERIFICATION_NOT_SUBMITTED,
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('verification:send-reminders')->assertExitCode(0);

    Notification::assertNothingSentTo($user);
});
