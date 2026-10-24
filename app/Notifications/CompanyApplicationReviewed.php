<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\CompanyApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyApplicationReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public CompanyApplication $application)
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
                ->subject(__('You are now a verified CarHub rental company'))
                ->line(__('Your rental company application has been approved. You can now list vehicles for rent.'))
                ->action(__('List a vehicle'), route('company.vehicles.create'));
        }

        return (new MailMessage)
            ->subject(__('Your CarHub rental company application needs attention'))
            ->line(__('We could not approve your rental company application.'))
            ->line(__('Reason: :reason', ['reason' => $this->application->rejection_reason]))
            ->action(__('Resubmit your application'), route('company.apply'));
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
            'title' => $approved ? __('You are a verified rental company') : __('Rental company application not approved'),
            'body' => $approved
                ? __('You can now list vehicles for rent.')
                : __('Reason: :reason', ['reason' => $this->application->rejection_reason]),
            'url' => $approved ? route('company.vehicles.index') : route('company.apply'),
            'company_application_id' => $this->application->id,
            'status' => $this->application->status->value,
        ];
    }
}
