<?php

namespace App\Actions\Companies;

use App\Enums\DocumentType;
use App\Models\CompanyApplication;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use LogicException;

class SubmitCompanyApplication
{
    /**
     * Open a new rental company application and store its documents privately.
     *
     * @param  array{company_name: string, business_address: string, notes?: string|null}  $details
     * @param  array<int, array{type: DocumentType, file: UploadedFile}>  $documents
     */
    public function handle(User $user, array $details, array $documents): CompanyApplication
    {
        if (! $user->isCompany()) {
            throw new LogicException('Only rental company accounts can apply for company verification.');
        }

        if ($user->isVerifiedCompany()) {
            throw new LogicException('The user is already a verified rental company.');
        }

        if ($user->companyApplications()->pending()->exists()) {
            throw new LogicException('The user already has an application awaiting review.');
        }

        return DB::transaction(function () use ($user, $details, $documents): CompanyApplication {
            $application = $user->companyApplications()->create([
                'company_name' => $details['company_name'],
                'business_address' => $details['business_address'],
                'notes' => $details['notes'] ?? null,
            ]);

            foreach ($documents as $document) {
                $application->documents()->create([
                    'user_id' => $user->id,
                    'type' => $document['type'],
                    'path' => $document['file']->store('documents/'.$user->id, VerificationDocument::DISK),
                    'original_name' => $document['file']->getClientOriginalName(),
                ]);
            }

            ActivityLogger::record('company_application.submitted', __(':name applied to verify :company.', ['name' => $user->name, 'company' => $details['company_name']]), $application, actor: $user);

            return $application;
        });
    }
}
