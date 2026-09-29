<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRequested extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Booking $booking)
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
        return (new MailMessage)
            ->subject(__('New booking request for your :vehicle', ['vehicle' => $this->booking->vehicle->name]))
            ->line(__(':renter wants to rent your :vehicle from :pickup to :return.', [
                'renter' => $this->booking->renter->name,
                'vehicle' => $this->booking->vehicle->name,
                'pickup' => $this->booking->pickup_at->format('M j, g:i A'),
                'return' => $this->booking->return_at->format('M j, g:i A'),
            ]))
            ->line(__('Rental subtotal: ₱:amount', ['amount' => number_format($this->booking->subtotal)]))
            ->action(__('Review the request'), route('owner.bookings.show', $this->booking));
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
        ];
    }
}
