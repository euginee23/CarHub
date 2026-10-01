<?php

use App\Http\Controllers\Api\TrackingPingController;
use App\Http\Middleware\AuthenticateGpsDevice;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Device API
|--------------------------------------------------------------------------
|
| Endpoints for hardware fitted to vehicles. Devices authenticate with their
| own bearer token (see AuthenticateGpsDevice), not a user session.
|
*/

Route::prefix('v1')->middleware([AuthenticateGpsDevice::class, 'throttle:gps'])->group(function () {
    Route::post('tracking/pings', TrackingPingController::class)->name('api.tracking.pings');
});
