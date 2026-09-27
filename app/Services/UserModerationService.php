<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;

/**
 * Owns the rules for suspending and reinstating an account, so a Filament
 * action is never more than a thin call into here (same pattern as
 * ReservationService, RatingService, etc.).
 */
class UserModerationService
{
    public function __construct(
        protected AuditLoggerService $auditLogger
    ) {}

    public function suspend(User $target, User $actor, string $reason): User
    {
        if ($target->id === $actor->id) {
            throw new InvalidArgumentException('You cannot suspend your own account.');
        }

        if ($target->isAdmin() && ! $actor->isAdmin()) {
            throw new InvalidArgumentException('Only an admin may suspend another admin account.');
        }

        if ($target->isSuspended()) {
            throw new InvalidArgumentException('This account is already suspended.');
        }

        $target->update([
            'suspended_at' => now(),
            'suspended_by' => $actor->id,
            'suspension_reason' => $reason,
        ]);

        $this->auditLogger->log(
            'USER_SUSPENDED',
            'User',
            $target->id,
            ['reason' => $reason],
            $actor
        );

        return $target;
    }

    public function reinstate(User $target, User $actor): User
    {
        if (! $target->isSuspended()) {
            throw new InvalidArgumentException('This account is not suspended.');
        }

        $target->update([
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);

        $this->auditLogger->log(
            'USER_REINSTATED',
            'User',
            $target->id,
            [],
            $actor
        );

        return $target;
    }
}
