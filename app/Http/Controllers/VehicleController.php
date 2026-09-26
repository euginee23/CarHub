<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\View\View;

class VehicleController extends Controller
{
    /**
     * Show a single listed vehicle.
     */
    public function show(Vehicle $vehicle): View
    {
        abort_unless($vehicle->isListed(), 404);

        $vehicle->load(['owner', 'photos']);

        $similar = Vehicle::listed()
            ->whereKeyNot($vehicle->getKey())
            ->with('coverPhoto')
            ->orderByRaw('case when type = ? then 1 else 0 end desc', [$vehicle->type->value])
            ->orderByDesc('rating')
            ->take(3)
            ->get();

        return view('pages::marketing.vehicle', [
            'vehicle' => $vehicle,
            'similar' => $similar,
            'ownerTrips' => (int) $vehicle->owner->vehicles()->sum('trips_count'),
        ]);
    }
}
