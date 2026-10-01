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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->content($notifiable);

        $message = (new MailMessage)->subject($content['title']);

        foreach ($content['lines'] as $line) {
            $message->line($line);
        }

        return $message->action(__('View booking'), $content['url']);
    }

    /**
     * Get the array representation of the notification, shown in the app.
     *
     * @return array{title: string, body: string, url: string, booking: string, status: string}
     */
    public function toArray(object $notifiable): array
    {
        $content = $this->content($notifiable);

        return [
            'title' => $content['title'],
            'body' => $content['lines'][0],
            'url' => $content['url'],
            'booking' => $this->booking->reference,
            'status' => $this->status->value,
        ];
    }

    /**
     * What to tell this recipient about the change.
     *
     * @return array{title: string, lines: array<int, string>, url: string}
     */
    protected function content(object $notifiable): array
    {
        $isOwner = $notifiable instanceof User && $notifiable->id === $this->booking->owner_id;
        $vehicle = $this->booking->vehicle->name;
        $pickup = $this->booking->pickup_at->format('M j, g:i A');

        [$title, $lines] = match ($this->status) {
            BookingStatus::Approved => [
                __('Your request was approved'),
                [
                    __('The owner approved your request for the :vehicle on :pickup.', ['vehicle' => $vehicle, 'pickup' => $pickup]),
                    __('Accept the rental terms, verify your two IDs, and sign the contract to continue to payment.'),
                ],
            ],
            BookingStatus::Declined => [
                __('Your request was declined'),
                [
                    __('Your request for the :vehicle was declined.', ['vehicle' => $vehicle]),
                    __('Reason: :reason', ['reason' => $this->booking->decline_reason ?? __('None given')]),
                ],
            ],
            BookingStatus::Confirmed => $isOwner
                ? [
                    __('Booking confirmed and paid'),
                    [
                        __(':renter paid ₱:total for your :vehicle. Hand it over on :pickup.', ['renter' => $this->booking->renter->name, 'total' => number_format($this->booking->total), 'vehicle' => $vehicle, 'pickup' => $pickup]),
                    ],
                ]
                : [
                    __('Payment received — booking confirmed'),
                    [
                        __('Your ₱:total payment went through. Your :vehicle is confirmed for :pickup.', ['total' => number_format($this->booking->total), 'vehicle' => $vehicle, 'pickup' => $pickup]),
                        __('The exact pickup point and the owner\'s contact number are now on your trip page.'),
                    ],
                ],
            BookingStatus::Cancelled => [
                __('Booking cancelled'),
                [__('The booking for the :vehicle on :pickup was cancelled.', ['vehicle' => $vehicle, 'pickup' => $pickup])],
            ],
            BookingStatus::Expired => [
                __('Booking expired'),
                [__('The booking for the :vehicle on :pickup expired before it was completed.', ['vehicle' => $vehicle, 'pickup' => $pickup])],
            ],
            default => [
                __('Booking update'),
                [__('The booking for the :vehicle is now: :status.', ['vehicle' => $vehicle, 'status' => $this->status->label()])],
            ],
        };

        return [
            'title' => $title,
            'lines' => $lines,
            'url' => $isOwner ? route('owner.bookings.show', $this->booking) : route('trips.show', $this->booking),
        ];
    }
}
