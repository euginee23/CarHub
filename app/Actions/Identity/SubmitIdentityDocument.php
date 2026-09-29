<?php

namespace App\Actions\Identity;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SubmitIdentityDocument
{
    /**
     * Store one of the renter's government IDs for review. A renter keeps at most
     * two IDs on file, each of a different type; rejected ones can be replaced.
     *
     * @throws ValidationException
     */
    public function handle(User $user, DocumentType $type, UploadedFile $file): VerificationDocument
    {
        if (! in_array($type, DocumentType::governmentIds(), true)) {
            throw ValidationException::withMessages(['type' => __('Choose a government-issued ID.')]);
        }

        $active = $user->identityDocuments()->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Approved]);

        if ((clone $active)->where('type', $type)->exists()) {
            throw ValidationException::withMessages(['type' => __('You already have a :type on file. Your two IDs must be different kinds.', ['type' => $type->label()])]);
        }

        if ((clone $active)->count() >= User::REQUIRED_IDENTITY_DOCUMENTS) {
            throw ValidationException::withMessages(['type' => __('You already have two IDs on file.')]);
        }

        return $user->identityDocuments()->create([
            'user_id' => $user->id,
            'type' => $type,
            'path' => $file->store('identity/'.$user->id, VerificationDocument::DISK),
            'original_name' => $file->getClientOriginalName(),
        ]);
    }
}
