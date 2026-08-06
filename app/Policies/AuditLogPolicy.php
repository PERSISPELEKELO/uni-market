<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can view audit log feeds.
     */
    public function viewAny(User $user): bool
    {
        return $user->isGovernanceCommittee() || $user->isAdmin();
    }

    /**
     * Determine whether the user can trigger cryptographic chain integrity verification.
     */
    public function verify(User $user): bool
    {
        return $user->isGovernanceCommittee() || $user->isAdmin();
    }
}
