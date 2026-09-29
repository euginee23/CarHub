<?php

namespace App\Actions\Owners;

use App\Enums\DocumentType;
use App\Models\OwnerApplication;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use LogicException;

class SubmitOwnerApplication
{
    /**
     * Open a new owner application and store its documents privately.
     *
     * @param  array<int, array{type: DocumentType, file: UploadedFile}>  $documents
     */
    public function handle(User $user, array $documents, ?string $notes = null): OwnerApplication
    {
        if ($user->is_admin) {
            throw new LogicException('Administrators cannot apply to become owners.');
        }

        if ($user->isVerifiedOwner()) {
            throw new LogicException('The user is already a verified owner.');
        }

        if ($user->ownerApplications()->pending()->exists()) {
            throw new LogicException('The user already has an application awaiting review.');
        }

        return DB::transaction(function () use ($user, $documents, $notes): OwnerApplication {
            $application = $user->ownerApplications()->create(['notes' => $notes]);

            foreach ($documents as $document) {
                $application->documents()->create([
                    'user_id' => $user->id,
                    'type' => $document['type'],
                    'path' => $document['file']->store('documents/'.$user->id, VerificationDocument::DISK),
                    'original_name' => $document['file']->getClientOriginalName(),
                ]);
            }

            return $application;
        });
    }
}
