<?php

namespace App\Actions\Tracking;

use App\Actions\Bookings\TransitionBooking;
use App\Enums\BookingStatus;
use App\Enums\FuelLevel;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Pricing\RentalQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Development helper for the GPS test page: put a vehicle on a 24-hour rental
 * right away, skipping request, checkout, and payment, so a tracker's positions
 * are recorded and can be watched on the map. Never available in production.
 */
class StartDemoTrip
{
    /**
     * The account demo trips are booked under.
     */
    public const string RENTER_EMAIL = 'demo-renter@carhub.test';

    public function __construct(protected TransitionBooking $transitions) {}

    /**
     * Start a demo trip on the vehicle.
     *
     * @throws ValidationException
     */
    public function handle(Vehicle $vehicle, User $administrator): Booking
    {
        self::ensureAllowed();

        $pickup = now()->startOfMinute();
        $return = $pickup->addDay();

        if (! Vehicle::whereKey($vehicle->getKey())->availableBetween($pickup, $return)->exists()) {
            throw ValidationException::withMessages(['demo' => __('This vehicle is booked or blocked in the next 24 hours, so a demo trip cannot start.')]);
        }

        return DB::transaction(function () use ($vehicle, $administrator, $pickup, $return): Booking {
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
                'renter_notes' => __('Demo trip for testing GPS tracking.'),
            ]);

            $booking->forceFill([
                'renter_id' => self::demoRenter()->id,
                'owner_id' => $vehicle->owner_id,
                'vehicle_id' => $vehicle->id,
                'status' => BookingStatus::Ongoing,
                'approved_at' => $pickup,
                'picked_up_at' => $pickup,
                'pickup_odometer' => 0,
                'pickup_fuel' => FuelLevel::Full,
            ])->save();

            // Recorded directly rather than transitioned, so nobody is emailed about a test.
            $this->transitions->record($booking, null, BookingStatus::Requested, $administrator, __('Demo trip created from the GPS test page.'));
            $this->transitions->record($booking, BookingStatus::Requested, BookingStatus::Ongoing, $administrator, __('Demo trip started; the vehicle is out for 24 hours.'));

            return $booking;
        });
    }

    /**
     * Whether a booking is one of these demo trips.
     */
    public static function isDemo(Booking $booking): bool
    {
        return $booking->renter()->where('email', self::RENTER_EMAIL)->exists();
    }

    /**
     * Refuse outside development, or when the GPS test page is switched off.
     */
    public static function ensureAllowed(): void
    {
        if (app()->isProduction() || ! config('carhub.tracking.test_page')) {
            throw new LogicException('Demo trips are only available on the GPS test page outside production.');
        }
    }

    /**
     * The renter account demo trips belong to.
     */
    protected static function demoRenter(): User
    {
        $renter = User::firstWhere('email', self::RENTER_EMAIL);

        if ($renter !== null) {
            return $renter;
        }

        $renter = new User(['name' => 'Demo Renter', 'email' => self::RENTER_EMAIL, 'password' => Hash::make(Str::random(40))]);
        $renter->forceFill(['role' => UserRole::Renter, 'email_verified_at' => now()])->save();

        return $renter;
    }
}
