<?php

namespace App\Actions\Companies;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentStatus;
use App\Models\CompanyApplication;
use App\Models\User;
use App\Notifications\CompanyApplicationReviewed;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReviewCompanyApplication
{
    /**
     * Approve the application, verifying the rental company and publishing its
     * business name and address.
     */
    public function approve(CompanyApplication $application, User $reviewer): void
    {
        $this->review($application, $reviewer, ApplicationStatus::Approved);
    }

    /**
     * Reject the application, recording why so the applicant can fix and resubmit.
     */
    public function reject(CompanyApplication $application, User $reviewer, string $reason): void
    {
        $this->review($application, $reviewer, ApplicationStatus::Rejected, $reason);
    }

    /**
     * Record the decision on the application, its documents, and the applicant.
     */
    protected function review(CompanyApplication $application, User $reviewer, ApplicationStatus $decision, ?string $reason = null): void
    {
        if (! $application->isPending()) {
            throw new LogicException('Only pending applications can be reviewed.');
        }

        DB::transaction(function () use ($application, $reviewer, $decision, $reason): void {
            $application->forceFill([
                'status' => $decision,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $application->documents()->update([
                'status' => $decision === ApplicationStatus::Approved ? DocumentStatus::Approved : DocumentStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            if ($decision === ApplicationStatus::Approved) {
                $application->user->forceFill([
                    'company_verified_at' => now(),
                    'company_name' => $application->company_name,
                    'company_address' => $application->business_address,
                ])->save();
            }
        });

        ActivityLogger::record(
            'company_application.'.$decision->value,
            __('Company application from :company :decision.', ['company' => $application->company_name ?? $application->user->name, 'decision' => mb_strtolower($decision->label())]),
            $application,
            array_filter(['reason' => $reason]),
            $reviewer,
        );

        $application->user->notify(new CompanyApplicationReviewed($application));
    }
}
