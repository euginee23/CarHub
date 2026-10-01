<?php

namespace App\Actions\Tracking;

use App\Models\GpsDevice;
use App\Models\Vehicle;

class ConnectGpsDevice
{
    /**
     * Pair a tracker with the vehicle, or issue a fresh token for the one already
     * paired (the old token stops working). Returns the plain-text token, which
     * is shown once and never stored.
     */
    public function handle(Vehicle $vehicle, ?string $label = null): string
    {
        $token = GpsDevice::newToken();

        $device = $vehicle->gpsDevice ?? new GpsDevice;
        $device->vehicle()->associate($vehicle);
        $device->label = $label ?? $device->label ?? __('GPS tracker');
        $device->token_hash = GpsDevice::hashToken($token);
        $device->save();

        $vehicle->setRelation('gpsDevice', $device);

        return $token;
    }
}
