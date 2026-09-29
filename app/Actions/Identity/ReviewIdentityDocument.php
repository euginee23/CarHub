<?php

namespace App\Actions\Identity;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Notifications\IdentityDocumentReviewed;
use LogicException;

class ReviewIdentityDocument
{
    /**
     * Accept a renter's ID as valid.
     */
    public function approve(VerificationDocument $document, User $reviewer): void
    {
        $this->review($document, $reviewer, DocumentStatus::Approved);
    }

    /**
     * Reject a renter's ID, telling them why so they can upload a replacement.
     */
    public function reject(VerificationDocument $document, User $reviewer, string $reason): void
    {
        $this->review($document, $reviewer, DocumentStatus::Rejected, $reason);
    }

    /**
     * Record the decision and notify the renter.
     */
    protected function review(VerificationDocument $document, User $reviewer, DocumentStatus $decision, ?string $reason = null): void
    {
        if ($document->status !== DocumentStatus::Pending) {
            throw new LogicException('Only pending documents can be reviewed.');
        }

        $document->forceFill([
            'status' => $decision,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $document->user->notify(new IdentityDocumentReviewed($document));
    }
}
