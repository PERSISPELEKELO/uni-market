<?php

namespace App\Policies;

use App\Models\StudentVerificationDocument;
use App\Models\User;

class StudentVerificationDocumentPolicy
{
    /**
     * Only staff may browse the verification queue in the admin panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isGovernanceCommittee();
    }

    /**
     * Only the student who submitted the document, or staff who review
     * verification requests, may ever see it.
     */
    public function view(User $user, StudentVerificationDocument $document): bool
    {
        return $user->id === $document->user_id || $user->isAdmin() || $user->isGovernanceCommittee();
    }

    /**
     * Only staff decide the outcome of a submission - and never their own,
     * so a reviewer can't approve or reject their own account's document.
     */
    public function review(User $user, StudentVerificationDocument $document): bool
    {
        if ($user->id === $document->user_id) {
            return false;
        }

        return $user->isAdmin() || $user->isGovernanceCommittee();
    }
}
