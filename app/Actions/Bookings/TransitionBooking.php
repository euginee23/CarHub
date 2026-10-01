<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingStatusUpdated;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

class TransitionBooking
{
    /**
     * Move a booking to a new status, record the step in its audit trail, and
     * tell the other party. Every status change goes through here.
     *
     * @param  array<string, mixed>  $attributes  Extra columns to set alongside the status.
     */
    public function handle(Booking $booking, BookingStatus $to, ?User $actor = null, ?string $note = null, array $attributes = []): Booking
    {
        $from = $booking->status;

        if (! $from->canTransitionTo($to)) {
            throw new LogicException("A booking cannot move from [{$from->value}] to [{$to->value}].");
        }

        DB::transaction(function () use ($booking, $from, $to, $actor, $note, $attributes): void {
            $booking->forceFill(['status' => $to, ...$attributes])->save();

            $this->record($booking, $from, $to, $actor, $note);
        });

        $this->notify($booking, $to, $actor);

        return $booking;
    }

    /**
     * Write one step of the audit trail.
     */
    public function record(Booking $booking, ?BookingStatus $from, BookingStatus $to, ?User $actor = null, ?string $note = null): void
    {
        $booking->statusChanges()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor?->id,
            'note' => $note,
        ]);

        ActivityLogger::record(
            'booking.'.$to->value,
            $from === null
                ? __('Booking :reference requested.', ['reference' => $booking->reference])
                : __('Booking :reference moved from :from to :to.', ['reference' => $booking->reference, 'from' => mb_strtolower($from->label()), 'to' => mb_strtolower($to->label())]),
            $booking,
            array_filter(['note' => $note]),
            $actor,
        );
    }

    /**
     * Notify whoever did not make the change. System changes (no actor) go to both parties.
     */
    protected function notify(Booking $booking, BookingStatus $status, ?User $actor): void
    {
        $recipients = match ($status) {
            BookingStatus::Approved, BookingStatus::Declined, BookingStatus::Expired => [$booking->renter],
            BookingStatus::AwaitingPayment => [],
            default => [$booking->renter, $booking->owner],
        };

        foreach ($recipients as $recipient) {
            if ($recipient->isNot($actor)) {
                $recipient->notify(new BookingStatusUpdated($booking, $status));
            }
        }
    }
}
