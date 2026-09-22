<?php

namespace App\Policies;

use App\Models\StudentVerificationDocument;
use App\Models\User;

class StudentVerificationDocumentPolicy
{
    /**
     * Only the student who submitted the document, or staff who review
     * verification requests, may ever see it.
     */
    public function view(User $user, StudentVerificationDocument $document): bool
    {
        return $user->id === $document->user_id || $user->isAdmin() || $user->isGovernanceCommittee();
    }

    /**
     * Only staff decide the outcome of a submission.
     */
    public function review(User $user): bool
    {
        return $user->isAdmin() || $user->isGovernanceCommittee();
    }
}
