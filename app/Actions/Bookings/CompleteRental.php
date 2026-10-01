<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\FuelLevel;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteRental
{
    public function __construct(protected TransitionBooking $transitions) {}

    /**
     * The owner takes the vehicle back: record the return time and condition and
     * close the rental. Tracking stops, and the vehicle is free to book again.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, User $owner, int $odometer, FuelLevel $fuel, ?string $notes = null): Booking
    {
        if ($booking->status !== BookingStatus::Ongoing) {
            throw ValidationException::withMessages(['handover' => __('Only vehicles out on a rental can be returned.')]);
        }

        if ($booking->pickup_odometer !== null && $odometer < $booking->pickup_odometer) {
            throw ValidationException::withMessages(['returnOdometer' => __('The odometer cannot read less than at pickup (:km km).', ['km' => number_format($booking->pickup_odometer)])]);
        }

        return DB::transaction(function () use ($booking, $owner, $odometer, $fuel, $notes): Booking {
            $booking->vehicle()->increment('trips_count');

            $distance = $booking->pickup_odometer !== null ? $odometer - $booking->pickup_odometer : null;

            return $this->transitions->handle(
                $booking,
                BookingStatus::Completed,
                $owner,
                $distance !== null
                    ? __('Vehicle returned at :odometer km (:distance km driven) with a :fuel.', ['odometer' => number_format($odometer), 'distance' => number_format($distance), 'fuel' => mb_strtolower($fuel->label())])
                    : __('Vehicle returned with a :fuel.', ['fuel' => mb_strtolower($fuel->label())]),
                [
                    'returned_at' => now(),
                    'return_odometer' => $odometer,
                    'return_fuel' => $fuel,
                    'return_notes' => $notes,
                ],
            );
        });
    }
}
