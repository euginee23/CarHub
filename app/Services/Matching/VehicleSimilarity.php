<?php

namespace App\Services\Matching;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Content-based filtering: ranks listed vehicles by how closely their
 * characteristics match a reference vehicle or a set of renter preferences.
 */
class VehicleSimilarity
{
    /**
     * The listed vehicles most similar to the given one.
     *
     * @return Collection<int, Vehicle>
     */
    public function similarTo(Vehicle $vehicle, int $limit = 3): Collection
    {
        return $this->rank(
            VehicleFeatureVector::forVehicle($vehicle),
            Vehicle::listed()->whereKeyNot($vehicle->getKey()),
            $limit,
        );
    }

    /**
     * The listed vehicles that best match a renter's preferences, optionally
     * excluding ones already shown to them.
     *
     * @param  array{type?: string|null, transmission?: string|null, fuel?: string|null, seats?: int|null, maxPrice?: int|null, features?: array<int, string>}  $preferences
     * @param  array<int, int>  $excludeIds
     * @return Collection<int, Vehicle>
     */
    public function matchingPreferences(array $preferences, array $excludeIds = [], int $limit = 3): Collection
    {
        $target = VehicleFeatureVector::forPreferences($preferences);

        if ($target->isEmpty()) {
            return new Collection;
        }

        return $this->rank($target, Vehicle::listed()->whereKeyNot($excludeIds), $limit);
    }

    /**
     * Score every candidate against the target vector and keep the best matches.
     * Each returned vehicle carries its score in the `similarity` attribute.
     *
     * @param  Builder<Vehicle>  $candidates
     * @return Collection<int, Vehicle>
     */
    protected function rank(VehicleFeatureVector $target, Builder $candidates, int $limit): Collection
    {
        return $candidates->with('coverPhoto')->get()
            ->each(function (Vehicle $candidate) use ($target): void {
                $candidate->setAttribute('similarity', round($target->cosineSimilarity(VehicleFeatureVector::forVehicle($candidate)), 4));
            })
            ->sortBy([
                ['similarity', 'desc'],
                ['rating', 'desc'],
            ])
            ->take($limit)
            ->values();
    }
}
