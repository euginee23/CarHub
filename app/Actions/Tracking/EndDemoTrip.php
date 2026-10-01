<?php

namespace App\Actions\Tracking;

use App\Actions\Bookings\TransitionBooking;
use App\Enums\BookingStatus;
use App\Enums\FuelLevel;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Close a demo trip started from the GPS test page. Tracking stops, the route
 * stays viewable like any finished rental, and the vehicle's real trip count
 * is left alone.
 */
class EndDemoTrip
{
    public function __construct(protected TransitionBooking $transitions) {}

    /**
     * End the demo trip.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, User $administrator): Booking
    {
        StartDemoTrip::ensureAllowed();

        if ($booking->status !== BookingStatus::Ongoing || ! StartDemoTrip::isDemo($booking)) {
            throw ValidationException::withMessages(['demo' => __('Only an ongoing demo trip can be ended here.')]);
        }

        $booking->forceFill([
            'status' => BookingStatus::Completed,
            'returned_at' => now(),
            'return_odometer' => $booking->pickup_odometer ?? 0,
            'return_fuel' => FuelLevel::Full,
        ])->save();

        $this->transitions->record($booking, BookingStatus::Ongoing, BookingStatus::Completed, $administrator, __('Demo trip ended from the GPS test page.'));

        return $booking;
    }
}
