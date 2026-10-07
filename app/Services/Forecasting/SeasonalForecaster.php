<?php

namespace App\Services\Forecasting;

use Carbon\CarbonImmutable;

/**
 * The fallback forecast when the LSTM cannot run or a body type has too little
 * history: the average for each day of the week over the last eight weeks
 * ("seasonal naive"). Simple, but it keeps weekday/weekend patterns.
 */
class SeasonalForecaster
{
    /**
     * Forecast `horizon` days after the last day of history.
     *
     * @param  array<string, int|float>  $history  Daily values keyed by Y-m-d, oldest first.
     * @return array<string, float> Keyed by Y-m-d.
     */
    public function forecast(array $history, CarbonImmutable $lastDay, int $horizon, int $weeks = 8): array
    {
        $recent = array_slice($history, -7 * $weeks, preserve_keys: true);
        $byWeekday = array_fill(0, 7, []);

        foreach ($recent as $day => $value) {
            $byWeekday[CarbonImmutable::parse($day)->dayOfWeek][] = (float) $value;
        }

        $averages = array_map(fn (array $values) => $values === [] ? 0.0 : array_sum($values) / count($values), $byWeekday);
        $forecast = [];

        for ($offset = 1; $offset <= $horizon; $offset++) {
            $day = $lastDay->addDays($offset);
            $forecast[$day->toDateString()] = round($averages[$day->dayOfWeek], 3);
        }

        return $forecast;
    }
}
