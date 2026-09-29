<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Booking $booking, public BookingStatus $status)
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $isOwner = $notifiable instanceof User && $notifiable->id === $this->booking->owner_id;
        $vehicle = $this->booking->vehicle->name;

        $message = (new MailMessage)
            ->subject(__('Booking :reference: :status', ['reference' => $this->booking->reference, 'status' => $this->status->label()]));

        $message = match ($this->status) {
            BookingStatus::Approved => $message
                ->line(__('Good news — the owner approved your request for the :vehicle.', ['vehicle' => $vehicle]))
                ->line(__('Accept the rental terms, verify your two IDs, and sign the contract to continue to payment.')),
            BookingStatus::Declined => $message
                ->line(__('Your request for the :vehicle was declined.', ['vehicle' => $vehicle]))
                ->line(__('Reason: :reason', ['reason' => $this->booking->decline_reason ?? __('None given')])),
            BookingStatus::Cancelled => $message
                ->line(__('The booking for the :vehicle from :pickup was cancelled.', ['vehicle' => $vehicle, 'pickup' => $this->booking->pickup_at->format('M j')])),
            BookingStatus::Expired => $message
                ->line(__('Your booking for the :vehicle expired before it was completed.', ['vehicle' => $vehicle])),
            default => $message
                ->line(__('The booking for the :vehicle is now: :status.', ['vehicle' => $vehicle, 'status' => $this->status->label()])),
        };

        return $message->action(
            __('View booking'),
            $isOwner ? route('owner.bookings.show', $this->booking) : route('trips.show', $this->booking),
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking' => $this->booking->reference,
            'status' => $this->status->value,
        ];
    }
}
