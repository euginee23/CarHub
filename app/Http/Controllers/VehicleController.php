<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\Matching\VehicleSimilarity;
use Illuminate\View\View;

class VehicleController extends Controller
{
    /**
     * Show a single listed vehicle.
     */
    public function show(Vehicle $vehicle, VehicleSimilarity $similarity): View
    {
        abort_unless($vehicle->isListed(), 404);

        $vehicle->load(['owner', 'photos']);

        return view('pages::marketing.vehicle', [
            'vehicle' => $vehicle,
            'similar' => $similarity->similarTo($vehicle),
            'ownerTrips' => (int) $vehicle->owner->vehicles()->sum('trips_count'),
        ]);
    }
}
