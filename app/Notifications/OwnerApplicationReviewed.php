<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\OwnerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerApplicationReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public OwnerApplication $application)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if ($this->application->status === ApplicationStatus::Approved) {
            return (new MailMessage)
                ->subject(__('You are now a verified CarHub owner'))
                ->line(__('Your owner application has been approved. You can now list vehicles for rent.'))
                ->action(__('List a vehicle'), route('owner.vehicles.create'));
        }

        return (new MailMessage)
            ->subject(__('Your CarHub owner application needs attention'))
            ->line(__('We could not approve your owner application.'))
            ->line(__('Reason: :reason', ['reason' => $this->application->rejection_reason]))
            ->action(__('Resubmit your application'), route('owner.apply'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $approved = $this->application->status === ApplicationStatus::Approved;

        return [
            'title' => $approved ? __('You are a verified owner') : __('Owner application not approved'),
            'body' => $approved
                ? __('You can now list vehicles for rent.')
                : __('Reason: :reason', ['reason' => $this->application->rejection_reason]),
            'url' => $approved ? route('owner.vehicles.index') : route('owner.apply'),
            'owner_application_id' => $this->application->id,
            'status' => $this->application->status->value,
        ];
    }
}
