<?php

namespace App\Actions\Vehicles;

use App\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleTakenDown;
use App\Support\ActivityLogger;

class ModerateVehicle
{
    /**
     * Take a listing off the marketplace for breaking the rules. The owner is told
     * why and cannot relist it until an administrator allows it. Existing
     * bookings are not affected.
     */
    public function takeDown(Vehicle $vehicle, User $administrator, string $reason): Vehicle
    {
        $vehicle->forceFill([
            'status' => VehicleStatus::Unlisted,
            'moderated_at' => now(),
            'moderation_reason' => $reason,
        ])->save();

        ActivityLogger::record('vehicle.taken_down', __('The :vehicle listing was taken down.', ['vehicle' => $vehicle->name]), $vehicle, ['reason' => $reason], $administrator);

        $vehicle->owner->notify(new VehicleTakenDown($vehicle));

        return $vehicle;
    }

    /**
     * Let the owner list the vehicle again once the problem is fixed.
     */
    public function allowRelisting(Vehicle $vehicle, User $administrator): Vehicle
    {
        $vehicle->forceFill(['moderated_at' => null, 'moderation_reason' => null])->save();

        ActivityLogger::record('vehicle.relisting_allowed', __('The owner of the :vehicle may list it again.', ['vehicle' => $vehicle->name]), $vehicle, actor: $administrator);

        return $vehicle;
    }
}
