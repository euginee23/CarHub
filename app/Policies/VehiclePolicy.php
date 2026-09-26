<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Determine whether the user can add vehicles to the marketplace.
     */
    public function create(User $user): bool
    {
        return $user->isVerifiedOwner();
    }

    /**
     * Determine whether the user can edit the vehicle's listing.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->isVerifiedOwner() && $vehicle->owner_id === $user->id;
    }

    /**
     * Determine whether the user can remove the vehicle.
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->update($user, $vehicle);
    }
}
