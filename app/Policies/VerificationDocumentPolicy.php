<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VerificationDocument;

class VerificationDocumentPolicy
{
    /**
     * Only the person who submitted a document, or an administrator, may open it.
     */
    public function view(User $user, VerificationDocument $document): bool
    {
        return $user->isAdmin() || $document->user_id === $user->id;
    }

    /**
     * Only administrators review identity and vehicle documents.
     */
    public function review(User $user, VerificationDocument $document): bool
    {
        return $user->isAdmin();
    }
}
