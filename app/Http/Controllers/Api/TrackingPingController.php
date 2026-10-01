<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tracking\RecordVehicleLocations;
use App\Http\Controllers\Controller;
use App\Models\GpsDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives GPS fixes from a vehicle's tracker (e.g. an ESP32 with a GPS module).
 *
 * The device sends JSON with its token from the owner's vehicle page:
 *
 *     POST /api/v1/tracking/pings
 *     Authorization: Bearer gps_xxxxxxxx
 *     Content-Type: application/json
 *
 *     {"lat": 10.3157, "lng": 123.8854, "speed": 42.5, "heading": 90}
 *
 * or, after being offline, a batch of buffered fixes (oldest first, up to 100):
 *
 *     {"pings": [{"lat": 10.31, "lng": 123.88, "recorded_at": "2026-10-01T08:00:00+08:00"}, ...]}
 *
 * `speed` is in km/h, `heading` in degrees (0–359), and `recorded_at` an ISO 8601
 * time; all three are optional. A sensible interval is one fix every 15–30 seconds.
 */
class TrackingPingController extends Controller
{
    /**
     * Store the device's fixes.
     */
    public function __invoke(Request $request, RecordVehicleLocations $recordVehicleLocations): JsonResponse
    {
        $single = ! $request->has('pings');

        $validated = $request->validate($single ? $this->pingRules('') : [
            'pings' => ['required', 'array', 'min:1', 'max:100'],
            ...$this->pingRules('pings.*.'),
        ]);

        $pings = $single ? [$validated] : $validated['pings'];

        /** @var GpsDevice $device */
        $device = $request->attributes->get('gpsDevice');

        $stored = $recordVehicleLocations->handle($device, $pings);

        return response()->json([
            'received' => count($pings),
            'stored' => $stored,
            'tracking' => $stored > 0,
        ], 202);
    }

    /**
     * The rules for one fix, with the given key prefix.
     *
     * @return array<string, array<int, string>>
     */
    protected function pingRules(string $prefix): array
    {
        return [
            $prefix.'lat' => ['required', 'numeric', 'between:-90,90'],
            $prefix.'lng' => ['required', 'numeric', 'between:-180,180'],
            $prefix.'speed' => ['nullable', 'numeric', 'between:0,400'],
            $prefix.'heading' => ['nullable', 'integer', 'between:0,359'],
            $prefix.'recorded_at' => ['nullable', 'date'],
        ];
    }
}
