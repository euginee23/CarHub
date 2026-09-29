<?php

namespace App\Support;

class Geo
{
    /**
     * Mean radius of the Earth, in kilometres.
     */
    public const float EARTH_RADIUS_KM = 6371.0;

    /**
     * The great-circle distance between two points, in kilometres (Haversine formula).
     */
    public static function distanceInKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * The latitude/longitude box that fully contains a circle of the given radius,
     * used to pre-filter candidates in SQL before measuring exact distances.
     *
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    public static function boundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $latDelta = rad2deg($radiusKm / self::EARTH_RADIUS_KM);
        $lngDelta = rad2deg($radiusKm / (self::EARTH_RADIUS_KM * max(cos(deg2rad($lat)), 0.000001)));

        return [
            'minLat' => $lat - $latDelta,
            'maxLat' => $lat + $latDelta,
            'minLng' => $lng - $lngDelta,
            'maxLng' => $lng + $lngDelta,
        ];
    }
}
