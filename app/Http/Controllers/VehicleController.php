<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Config;
use Illuminate\View\View;

class VehicleController extends Controller
{
    /**
     * Show a single vehicle listing.
     *
     * Vehicles are read from config/demo.php while the rental domain is still being
     * designed; this becomes a route-model-bound Vehicle once the models exist.
     */
    public function show(string $slug): View
    {
        $vehicle = collect(Config::array('demo.vehicles'))->firstWhere('slug', $slug);

        abort_if($vehicle === null, 404);

        return view('pages::marketing.vehicle', ['vehicle' => $vehicle]);
    }
}
