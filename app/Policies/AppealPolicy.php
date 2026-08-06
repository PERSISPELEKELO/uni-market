<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ActorRole;
use App\Models\Appeal;
use App\Models\User;

class AppealPolicy
{
    /**
     * Determine whether the user can view any appeals.
     * Only users with governance_committee or admin roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->isGovernanceCommittee() || $user->isAdmin() || $user->role === ActorRole::GOVERNANCE_COMMITTEE->value || $user->role === ActorRole::ADMIN->value;
    }

    /**
     * Determine whether the user can view a specific appeal.
     */
    public function view(User $user, Appeal $appeal): bool
    {
        return $user->id === $appeal->user_id || $this->viewAny($user);
    }

    /**
     * Determine whether the user can create an appeal.
     * Standard student users who own the sanctioned target item.
     */
    public function create(User $user): bool
    {
        return $user->isStudent() || $user->role === ActorRole::STUDENT->value || empty($user->role);
    }

    /**
     * Determine whether the user can resolve an appeal.
     * Only governance_committee or admin members.
     */
    public function resolve(User $user, ?Appeal $appeal = null): bool
    {
        return $user->isGovernanceCommittee() || $user->isAdmin() || $user->role === ActorRole::GOVERNANCE_COMMITTEE->value || $user->role === ActorRole::ADMIN->value;
    }

    /**
     * Legacy alias for review / decide.
     */
    public function review(User $user, Appeal $appeal): bool
    {
        return $this->resolve($user, $appeal);
    }

    public function decide(User $user, Appeal $appeal): bool
    {
        return $this->resolve($user, $appeal);
    }
}
