<?php

namespace App\Models;

use Database\Factories\GpsDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The GPS tracker (an ESP32 or similar) fitted to a vehicle. It authenticates with
 * a bearer token; only the token's SHA-256 hash is stored.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property string|null $label
 * @property string $token_hash
 * @property Carbon|null $last_seen_at
 * @property float|null $last_latitude
 * @property float|null $last_longitude
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vehicle $vehicle
 */
#[Fillable(['label'])]
#[Hidden(['token_hash'])]
class GpsDevice extends Model
{
    /** @use HasFactory<GpsDeviceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
        ];
    }

    /**
     * The vehicle the tracker is fitted to.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Find the device a plain-text token belongs to.
     */
    public static function findByToken(string $token): ?self
    {
        return static::firstWhere('token_hash', static::hashToken($token));
    }

    /**
     * Generate a new plain-text device token.
     */
    public static function newToken(): string
    {
        return 'gps_'.Str::random(40);
    }

    /**
     * Hash a plain-text token for storage and lookup.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Whether the tracker has reported recently enough to be considered online.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(5));
    }
}
