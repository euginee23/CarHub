<?php

namespace App\Support;

use App\Models\GpsDevice;
use Illuminate\Support\Facades\Cache;

/**
 * A short, rolling record of what each tracker sent, for debugging a device on
 * the GPS test page. Kept in the cache for two hours. Coordinates are only
 * kept for fixes that were stored anyway — during a rental.
 */
class TrackerRequestLog
{
    /**
     * How many requests are kept per device.
     */
    public const int LIMIT = 25;

    /**
     * How long the log is kept, in seconds.
     */
    public const int TTL = 7200;

    /**
     * Add a request to the device's log, newest first.
     *
     * @param  array{status: int, received: int, stored: int, error?: string|null, lat?: float|null, lng?: float|null, ip?: string|null, agent?: string|null}  $entry
     */
    public static function record(GpsDevice $device, array $entry): void
    {
        $entries = self::for($device);

        array_unshift($entries, [
            'at' => now()->toIso8601String(),
            'status' => $entry['status'],
            'received' => $entry['received'],
            'stored' => $entry['stored'],
            'error' => $entry['error'] ?? null,
            'lat' => $entry['stored'] > 0 ? ($entry['lat'] ?? null) : null,
            'lng' => $entry['stored'] > 0 ? ($entry['lng'] ?? null) : null,
            'ip' => $entry['ip'] ?? null,
            'agent' => isset($entry['agent']) ? mb_substr($entry['agent'], 0, 80) : null,
        ]);

        Cache::put(self::key($device), array_slice($entries, 0, self::LIMIT), self::TTL);
    }

    /**
     * The device's logged requests, newest first.
     *
     * @return array<int, array{at: string, status: int, received: int, stored: int, error: string|null, lat: float|null, lng: float|null, ip: string|null, agent: string|null}>
     */
    public static function for(GpsDevice $device): array
    {
        $entries = Cache::get(self::key($device), []);

        return is_array($entries) ? $entries : [];
    }

    /**
     * Forget the device's log.
     */
    public static function clear(GpsDevice $device): void
    {
        Cache::forget(self::key($device));
    }

    /**
     * The cache key for a device's log.
     */
    protected static function key(GpsDevice $device): string
    {
        return 'tracker-requests:'.$device->id;
    }
}
