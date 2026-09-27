<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Only admins manage accounts (suspend/reinstate). Governance-committee
     * members review disputes and appeals, not user accounts.
     */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    public function suspend(User $actor, User $target): bool
    {
        return $this->manage($actor) && $actor->id !== $target->id;
    }

    public function reinstate(User $actor, User $target): bool
    {
        return $this->manage($actor) && $actor->id !== $target->id;
    }
}
