<?php

namespace App\Actions\Tracking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\GpsDevice;
use Carbon\CarbonImmutable;

class RecordVehicleLocations
{
    /**
     * Store the fixes a tracker reported. Location is only kept while the vehicle
     * is out on a rental — renters consent to tracking for the trip, not beyond
     * it — so fixes outside an ongoing rental are acknowledged and dropped.
     * Returns how many fixes were stored.
     *
     * @param  array<int, array{lat: float, lng: float, speed?: float|null, heading?: int|null, recorded_at?: string|null}>  $pings
     */
    public function handle(GpsDevice $device, array $pings): int
    {
        $device->forceFill(['last_seen_at' => now()])->save();

        $booking = Booking::where('vehicle_id', $device->vehicle_id)
            ->where('status', BookingStatus::Ongoing)
            ->first();

        if ($booking === null) {
            return 0;
        }

        $rows = collect($pings)
            ->map(fn (array $ping) => [
                'vehicle_id' => $device->vehicle_id,
                'booking_id' => $booking->id,
                'latitude' => round((float) $ping['lat'], 7),
                'longitude' => round((float) $ping['lng'], 7),
                'speed_kph' => isset($ping['speed']) ? round((float) $ping['speed'], 2) : null,
                'heading' => isset($ping['heading']) ? (int) $ping['heading'] : null,
                // Buffered fixes from before the trip started are not the renter's trip.
                'recorded_at' => CarbonImmutable::parse($ping['recorded_at'] ?? now())->max($booking->picked_up_at ?? $booking->pickup_at)->min(now()),
                'created_at' => now(),
            ])
            ->sortBy('recorded_at')
            ->values();

        $booking->locations()->insert($rows->all());

        $latest = $rows->last();

        $device->forceFill([
            'last_latitude' => $latest['latitude'],
            'last_longitude' => $latest['longitude'],
        ])->save();

        return $rows->count();
    }
}
