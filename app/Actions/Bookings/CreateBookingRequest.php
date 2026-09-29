<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingRequested;
use App\Services\Availability\AvailabilityChecker;
use App\Services\Pricing\RentalQuote;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBookingRequest
{
    public function __construct(
        protected AvailabilityChecker $availability,
        protected TransitionBooking $transitions,
        protected ApproveBooking $approveBooking,
    ) {}

    /**
     * Validate the requested schedule and record a booking request. Instant-book
     * vehicles are approved straight away; the rest wait for the owner.
     *
     * @throws ValidationException
     */
    public function handle(User $renter, Vehicle $vehicle, CarbonInterface $pickup, CarbonInterface $return, ?string $notes = null): Booking
    {
        if ($vehicle->owner_id === $renter->id) {
            throw ValidationException::withMessages(['schedule' => __('You cannot book your own vehicle.')]);
        }

        if ($errors = $this->availability->scheduleErrors($pickup, $return)) {
            throw ValidationException::withMessages(['schedule' => $errors]);
        }

        $alreadyRequested = $renter->bookings()
            ->whereBelongsTo($vehicle)
            ->whereIn('status', [BookingStatus::Requested, ...BookingStatus::holding()])
            ->overlapping($pickup, $return)
            ->exists();

        if ($alreadyRequested) {
            throw ValidationException::withMessages(['schedule' => __('You already have a booking for this vehicle on those dates.')]);
        }

        $booking = DB::transaction(function () use ($renter, $vehicle, $pickup, $return, $notes): Booking {
            // Lock the vehicle so two renters cannot slip through the check at once.
            $vehicle = Vehicle::whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->availability->isAvailable($vehicle, $pickup, $return)) {
                throw ValidationException::withMessages(['schedule' => __('This vehicle is not available for all of those dates. Try different days.')]);
            }

            $quote = RentalQuote::for($vehicle, $pickup, $return);

            $booking = new Booking([
                'pickup_at' => $pickup,
                'return_at' => $return,
                'pickup_location' => $vehicle->location,
                'daily_rate' => $quote->dailyRate,
                'days' => $quote->days,
                'subtotal' => $quote->subtotal,
                'service_fee' => $quote->serviceFee,
                'total' => $quote->total,
                'renter_notes' => $notes,
            ]);

            $booking->forceFill([
                'renter_id' => $renter->id,
                'owner_id' => $vehicle->owner_id,
                'vehicle_id' => $vehicle->id,
                'status' => BookingStatus::Requested,
            ])->save();

            $this->transitions->record($booking, null, BookingStatus::Requested, $renter);

            return $booking;
        });

        if ($vehicle->instant_book) {
            return $this->approveBooking->handle($booking, note: __('Approved automatically — instant book.'));
        }

        $booking->owner->notify(new BookingRequested($booking));

        return $booking;
    }
}
