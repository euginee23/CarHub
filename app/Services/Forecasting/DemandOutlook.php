<?php

namespace App\Services\Forecasting;

use App\Enums\VehicleType;
use App\Models\DemandForecast;
use App\Services\Reports\RentalReports;
use Carbon\CarbonImmutable;

/**
 * Reads the stored forecast: how demand for a body type over the coming days
 * compares with its recent past. Used for price suggestions and dashboards.
 */
class DemandOutlook
{
    /**
     * Expected requests per day over the next `days`, against the average of the
     * last eight weeks. `change` is a fraction: 0.12 means 12% busier than usual.
     *
     * @return array{expected: float, recent: float, change: float, method: string}|null
     */
    public function forType(VehicleType $type, int $days = 14): ?array
    {
        $today = CarbonImmutable::today();

        $forecast = DemandForecast::where('vehicle_type', $type)
            ->whereBetween('date', [$today->toDateString(), $today->addDays($days - 1)->toDateString()])
            ->get(['predicted_requests', 'method']);

        if ($forecast->isEmpty()) {
            return null;
        }

        $history = (new RentalReports($today->subDays(56), $today->subDay()))->dailyDemandByType();
        $recent = array_sum(array_map(fn (array $types) => $types[$type->value] ?? 0, $history)) / max(1, count($history));
        $expected = (float) $forecast->avg('predicted_requests');

        return [
            'expected' => round($expected, 2),
            'recent' => round($recent, 2),
            'change' => $recent > 0 ? round(($expected - $recent) / $recent, 3) : 0.0,
            'method' => (string) $forecast->first()->method,
        ];
    }
}
