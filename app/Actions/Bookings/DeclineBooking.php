<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeclineBooking
{
    public function __construct(protected TransitionBooking $transitions) {}

    /**
     * Turn down a booking request, telling the renter why.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, User $owner, string $reason): Booking
    {
        if ($booking->status !== BookingStatus::Requested) {
            throw ValidationException::withMessages(['booking' => __('This request has already been answered.')]);
        }

        return $this->transitions->handle($booking, BookingStatus::Declined, $owner, $reason, ['decline_reason' => $reason]);
    }
}
