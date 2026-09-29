<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelBooking
{
    public function __construct(protected TransitionBooking $transitions) {}

    /**
     * Cancel a booking before any payment has been taken.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        if (! $booking->isCancellableByRenter()) {
            throw ValidationException::withMessages(['booking' => __('This booking can no longer be cancelled here.')]);
        }

        return $this->transitions->handle($booking, BookingStatus::Cancelled, $actor, $reason, [
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
            'cancellation_reason' => $reason,
        ]);
    }
}
