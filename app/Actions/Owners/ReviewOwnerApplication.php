<?php

namespace App\Actions\Owners;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentStatus;
use App\Models\OwnerApplication;
use App\Models\User;
use App\Notifications\OwnerApplicationReviewed;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReviewOwnerApplication
{
    /**
     * Approve the application and verify the applicant as a vehicle owner.
     */
    public function approve(OwnerApplication $application, User $reviewer): void
    {
        $this->review($application, $reviewer, ApplicationStatus::Approved);
    }

    /**
     * Reject the application, recording why so the applicant can fix and resubmit.
     */
    public function reject(OwnerApplication $application, User $reviewer, string $reason): void
    {
        $this->review($application, $reviewer, ApplicationStatus::Rejected, $reason);
    }

    /**
     * Record the decision on the application, its documents, and the applicant.
     */
    protected function review(OwnerApplication $application, User $reviewer, ApplicationStatus $decision, ?string $reason = null): void
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
                $application->user->forceFill(['owner_verified_at' => now()])->save();
            }
        });

        ActivityLogger::record(
            'owner_application.'.$decision->value,
            __('Owner application from :name :decision.', ['name' => $application->user->name, 'decision' => mb_strtolower($decision->label())]),
            $application,
            array_filter(['reason' => $reason]),
            $reviewer,
        );

        $application->user->notify(new OwnerApplicationReviewed($application));
    }
}
