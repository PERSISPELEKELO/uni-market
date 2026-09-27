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

    /**
     * Permanently deletes an account. Refuses to touch one with any real
     * marketplace footprint - a `transactions` or `disputes` row referencing
     * this user would fail at the database level anyway (those foreign keys
     * are RESTRICT, not CASCADE), and even where the database would allow it
     * (listings, messages, ratings, appeals, verification documents all
     * CASCADE), silently wiping another party's conversation or rating
     * history as a side effect of deleting someone else's account is not
     * something a confirmation dialog can meaningfully warn about. Suspend
     * is the only option once an account has done anything at all - this
     * is for cleaning up an account that never did.
     */
    public function delete(User $target, User $actor): void
    {
        if ($target->id === $actor->id) {
            throw new InvalidArgumentException('You cannot delete your own account.');
        }

        if ($target->isAdmin() && ! $actor->isAdmin()) {
            throw new InvalidArgumentException('Only an admin may delete another admin account.');
        }

        if ($this->hasMarketplaceHistory($target)) {
            throw new InvalidArgumentException('This account has marketplace history (listings, transactions, messages, ratings, disputes or appeals) and cannot be permanently deleted. Suspend it instead.');
        }

        $this->auditLogger->log(
            'USER_DELETED',
            'User',
            $target->id,
            ['name' => $target->name, 'email' => $target->email, 'role' => $target->role],
            $actor
        );

        $target->delete();
    }

    private function hasMarketplaceHistory(User $target): bool
    {
        return $target->listings()->exists()
            || $target->purchases()->exists()
            || $target->sales()->exists()
            || $target->disputes()->exists()
            || $target->appeals()->exists()
            || $target->sentMessages()->exists()
            || $target->receivedMessages()->exists()
            || $target->ratingsGiven()->exists()
            || $target->ratingsReceived()->exists()
            || $target->verificationDocuments()->exists();
    }
}
