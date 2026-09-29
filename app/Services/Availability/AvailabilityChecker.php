<?php

namespace App\Services\Availability;

use App\Models\Vehicle;
use App\Models\VehicleBlackout;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

class AvailabilityChecker
{
    /**
     * Check a requested rental window against the platform's scheduling rules,
     * returning a human-readable message for every rule it breaks.
     *
     * @return array<int, string>
     */
    public function scheduleErrors(CarbonInterface $pickup, CarbonInterface $return, ?CarbonInterface $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $rules = config('carhub.booking');
        $errors = [];

        if ($pickup->lt($now->addHours($rules['min_lead_hours']))) {
            $errors[] = __('Pickup must be at least :hours hours from now.', ['hours' => $rules['min_lead_hours']]);
        }

        if ($pickup->gt($now->addDays($rules['max_advance_days']))) {
            $errors[] = __('Bookings open up to :days days ahead.', ['days' => $rules['max_advance_days']]);
        }

        if ($return->lte($pickup)) {
            $errors[] = __('The return must be after pickup.');
        } elseif ($pickup->diffInHours($return) < $rules['min_rental_hours']) {
            $errors[] = __('The minimum rental is :hours hours.', ['hours' => $rules['min_rental_hours']]);
        } elseif ($pickup->diffInDays($return) > $rules['max_rental_days']) {
            $errors[] = __('The maximum rental is :days days.', ['days' => $rules['max_rental_days']]);
        }

        return $errors;
    }

    /**
     * Determine whether the vehicle is listed and free for the whole rental window.
     */
    public function isAvailable(Vehicle $vehicle, CarbonInterface $pickup, CarbonInterface $return): bool
    {
        return $vehicle->isListed()
            && Vehicle::whereKey($vehicle->getKey())->availableBetween($pickup, $return)->exists();
    }

    /**
     * Every calendar day between the two dates on which the vehicle cannot be rented.
     *
     * @return array<string, true> Keyed by Y-m-d date.
     */
    public function unavailableDates(Vehicle $vehicle, CarbonInterface $from, CarbonInterface $to): array
    {
        $dates = [];

        $vehicle->blackouts()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->get()
            ->each(function (VehicleBlackout $blackout) use (&$dates, $from, $to): void {
                $period = CarbonPeriod::create(
                    max($blackout->starts_on->toDateString(), $from->toDateString()),
                    min($blackout->ends_on->toDateString(), $to->toDateString()),
                );

                foreach ($period as $day) {
                    $dates[$day->toDateString()] = true;
                }
            });

        return $dates;
    }
}
