<?php

namespace App\Notifications;

use App\Enums\DocumentStatus;
use App\Models\VerificationDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IdentityDocumentReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public VerificationDocument $document)
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
        $type = $this->document->type->label();

        if ($this->document->status === DocumentStatus::Approved) {
            return (new MailMessage)
                ->subject(__('Your :type was approved', ['type' => $type]))
                ->line(__('Your :type has been verified.', ['type' => $type]))
                ->action(__('View your IDs'), route('identity.edit'));
        }

        return (new MailMessage)
            ->subject(__('Your :type could not be verified', ['type' => $type]))
            ->line(__('Reason: :reason', ['reason' => $this->document->rejection_reason]))
            ->line(__('Please upload a clearer or different ID.'))
            ->action(__('Upload a replacement'), route('identity.edit'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $approved = $this->document->status === DocumentStatus::Approved;

        return [
            'title' => $approved ? __('ID approved') : __('ID not accepted'),
            'body' => $approved
                ? __('Your :type has been verified.', ['type' => $this->document->type->label()])
                : __('Your :type could not be verified: :reason', ['type' => $this->document->type->label(), 'reason' => $this->document->rejection_reason]),
            'url' => route('identity.edit'),
            'document_id' => $this->document->id,
            'status' => $this->document->status->value,
        ];
    }
}
