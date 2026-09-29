<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Availability\AvailabilityChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveBooking
{
    public function __construct(
        protected AvailabilityChecker $availability,
        protected TransitionBooking $transitions,
    ) {}

    /**
     * Accept a booking request. The vehicle is re-checked under a lock, and any
     * other pending requests for overlapping dates are declined automatically.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, ?User $owner = null, ?string $note = null): Booking
    {
        $declined = DB::transaction(function () use ($booking, $owner, $note) {
            $vehicle = Vehicle::whereKey($booking->vehicle_id)->lockForUpdate()->firstOrFail();
            $booking->refresh();

            if ($booking->status !== BookingStatus::Requested) {
                throw ValidationException::withMessages(['booking' => __('This request has already been answered.')]);
            }

            if ($booking->pickup_at->isPast()) {
                throw ValidationException::withMessages(['booking' => __('The pickup time for this request has already passed.')]);
            }

            if (! $this->availability->isAvailable($vehicle, $booking->pickup_at, $booking->return_at, ignoreBookingId: $booking->id)) {
                throw ValidationException::withMessages(['booking' => __('The vehicle is no longer free for these dates.')]);
            }

            $this->transitions->handle($booking, BookingStatus::Approved, $owner, $note, ['approved_at' => now()]);

            return Booking::whereBelongsTo($vehicle)
                ->whereKeyNot($booking->id)
                ->where('status', BookingStatus::Requested)
                ->overlapping($booking->pickup_at, $booking->return_at)
                ->get();
        });

        foreach ($declined as $competingRequest) {
            $this->transitions->handle($competingRequest, BookingStatus::Declined, note: __('Another request was approved for these dates.'), attributes: [
                'decline_reason' => __('The vehicle was booked by someone else for these dates.'),
            ]);
        }

        return $booking;
    }
}
