<?php

namespace App\Http\Controllers;

use App\Models\Review;
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

        $vehicle->load(['company', 'photos']);

        return view('pages::marketing.vehicle', [
            'vehicle' => $vehicle,
            'similar' => $similarity->similarTo($vehicle),
            'companyTrips' => (int) $vehicle->company->vehicles()->sum('trips_count'),
            'reviews' => $vehicle->reviews()->with('reviewer')->take(6)->get(),
            'companyRating' => Review::where('company_id', $vehicle->company_id)->avg('rating'),
        ]);
    }
}
