<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\FuelLevel;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StartRental
{
    public function __construct(protected TransitionBooking $transitions) {}

    /**
     * The owner hands the vehicle over: record the actual pickup time and the
     * vehicle's condition, and start the rental.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, User $owner, int $odometer, FuelLevel $fuel, ?string $notes = null): Booking
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw ValidationException::withMessages(['handover' => __('Only confirmed, paid bookings can be handed over.')]);
        }

        $earliest = $booking->pickup_at->subMinutes((int) config('carhub.handover.early_release_minutes'));

        if (now()->lt($earliest)) {
            throw ValidationException::withMessages(['handover' => __('The vehicle can be released from :time.', ['time' => $earliest->format('M j, g:i A')])]);
        }

        return $this->transitions->handle(
            $booking,
            BookingStatus::Ongoing,
            $owner,
            __('Vehicle released at :odometer km with a :fuel.', ['odometer' => number_format($odometer), 'fuel' => mb_strtolower($fuel->label())]),
            [
                'picked_up_at' => now(),
                'pickup_odometer' => $odometer,
                'pickup_fuel' => $fuel,
                'pickup_notes' => $notes,
            ],
        );
    }
}
