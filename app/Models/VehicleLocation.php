<?php

namespace App\Models;

use Database\Factories\VehicleLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One GPS fix reported by a vehicle's tracker during an active rental. Fixes are
 * kept for 30 days for dispute resolution, then pruned.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int $booking_id
 * @property float $latitude
 * @property float $longitude
 * @property float|null $speed_kph
 * @property int|null $heading
 * @property Carbon $recorded_at
 * @property Carbon $created_at
 * @property-read Vehicle $vehicle
 * @property-read Booking $booking
 */
#[Fillable(['latitude', 'longitude', 'speed_kph', 'heading', 'recorded_at'])]
class VehicleLocation extends Model
{
    /** @use HasFactory<VehicleLocationFactory> */
    use HasFactory, MassPrunable;

    /**
     * How long trip location history is kept, in days.
     */
    public const int RETENTION_DAYS = 30;

    /**
     * The name of the "updated at" column; fixes are never edited.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'speed_kph' => 'float',
            'heading' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * The vehicle that reported the fix.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The rental the fix was recorded during.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Fixes past the retention period.
     *
     * @return Builder<VehicleLocation>
     */
    public function prunable(): Builder
    {
        return static::where('recorded_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
