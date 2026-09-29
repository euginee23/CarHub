<?php

namespace App\Models;

use Database\Factories\VehicleBlackoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A date range, inclusive of both ends, when the owner has blocked a vehicle.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vehicle $vehicle
 */
#[Fillable(['starts_on', 'ends_on', 'reason'])]
class VehicleBlackout extends Model
{
    /** @use HasFactory<VehicleBlackoutFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * The vehicle that is blocked.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Only blackouts that touch any day between the two dates, inclusive.
     *
     * @param  Builder<VehicleBlackout>  $query
     */
    public function scopeOverlapping(Builder $query, string $fromDate, string $toDate): void
    {
        $query->whereDate('starts_on', '<=', $toDate)->whereDate('ends_on', '>=', $fromDate);
    }
}
