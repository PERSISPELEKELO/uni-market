<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\StudentVerificationReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nudges a student who confirmed their email but still hasn't submitted a
 * student ID document, at 24 hours and again at 3 days after they
 * registered. Stops automatically once they submit, because that changes
 * their student_verification_status away from NOT_SUBMITTED. Running daily
 * means either reminder can go out up to a day later than its exact mark -
 * acceptable for a nudge, and consistent with this app's other daily jobs.
 */
class SendVerificationReminders extends Command
{
    protected $signature = 'verification:send-reminders';

    protected $description = "Remind students who haven't submitted a student ID document yet";

    public function handle(): int
    {
        $sent24h = $this->remindStage(
            cutoff: now()->subHours(24),
            column: 'student_verification_reminder_24h_sent_at',
            stage: StudentVerificationReminder::STAGE_24_HOURS
        );

        $sent3d = $this->remindStage(
            cutoff: now()->subDays(3),
            column: 'student_verification_reminder_3d_sent_at',
            stage: StudentVerificationReminder::STAGE_3_DAYS
        );

        $this->info("Sent {$sent24h} 24-hour and {$sent3d} 3-day verification reminder(s).");

        return self::SUCCESS;
    }

    private function remindStage(Carbon $cutoff, string $column, string $stage): int
    {
        $users = User::whereNotNull('email_verified_at')
            ->where('student_verification_status', User::STUDENT_VERIFICATION_NOT_SUBMITTED)
            ->where('created_at', '<=', $cutoff)
            ->whereNull($column)
            ->get();

        foreach ($users as $user) {
            try {
                $user->notify(new StudentVerificationReminder($stage));
            } catch (\Throwable $exception) {
                report($exception);
            }

            $user->forceFill([$column => now()])->save();
        }

        return $users->count();
    }
}
