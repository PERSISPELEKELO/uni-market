<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Auth\Events\Verified;

/**
 * Members earn the "Official Student" badge by confirming their email address
 * (and, when STUDENT_EMAIL_DOMAINS is configured, by using a university address).
 */
class GrantVerifiedBadge
{
    public function __construct(protected AuditLoggerService $auditLogger) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->is_verified || ! $user->hasStudentEmailDomain()) {
            return;
        }

        $user->forceFill(['is_verified' => true])->save();

        $this->auditLogger->recordAction($user, 'USER_EMAIL_VERIFIED', 'User', (string) $user->id, [
            'is_verified' => true,
        ]);
    }
}
