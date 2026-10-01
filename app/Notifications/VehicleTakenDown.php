<?php

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VehicleTakenDown extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Vehicle $vehicle)
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
        return (new MailMessage)
            ->subject(__('Your :vehicle listing was taken down', ['vehicle' => $this->vehicle->name]))
            ->line(__('An administrator removed your :vehicle from the marketplace.', ['vehicle' => $this->vehicle->name]))
            ->line(__('Reason: :reason', ['reason' => $this->vehicle->moderation_reason]))
            ->line(__('Existing bookings are not affected. Reply to CarHub support once the issue is fixed.'))
            ->action(__('View your vehicle'), route('owner.vehicles.edit', $this->vehicle));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Listing taken down'),
            'body' => __('Your :vehicle was removed from the marketplace: :reason', ['vehicle' => $this->vehicle->name, 'reason' => $this->vehicle->moderation_reason]),
            'url' => route('owner.vehicles.edit', $this->vehicle),
            'vehicle_id' => $this->vehicle->id,
        ];
    }
}
