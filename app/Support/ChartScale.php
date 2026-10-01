<?php

namespace App\Support;

/**
 * Axis maths and number formatting for the admin report charts: clean round
 * tick values and compact labels (1,284 / 12.9K / 4.2M).
 */
class ChartScale
{
    /**
     * The smallest "nice" number (1, 2, 2.5, 5 × 10ⁿ) at or above the value.
     */
    public static function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 1;
        }

        $magnitude = 10 ** floor(log10($value));

        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($value <= $step * $magnitude) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    }

    /**
     * Evenly spaced tick values from zero to the nice maximum.
     *
     * @return array<int, float>
     */
    public static function ticks(float $max, int $count = 4): array
    {
        $top = self::niceMax($max);

        return array_map(fn (int $index) => $top / $count * $index, range(0, $count));
    }

    /**
     * A short label for a number, optionally prefixed (e.g. ₱).
     */
    public static function compact(float $value, string $prefix = ''): string
    {
        $abs = abs($value);

        $formatted = match (true) {
            $abs >= 1_000_000 => rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').'M',
            $abs >= 10_000 => rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.').'K',
            default => number_format($value, $value == floor($value) ? 0 : 1),
        };

        return $prefix.$formatted;
    }
}
