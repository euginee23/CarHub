<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A signed-in renter opening a vehicle's page: a behaviour signal for
 * recommendations. Kept for 180 days.
 *
 * @property int $id
 * @property int $user_id
 * @property int $vehicle_id
 * @property Carbon $viewed_at
 * @property-read User $user
 * @property-read Vehicle $vehicle
 */
class VehicleView extends Model
{
    use MassPrunable;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * The renter who viewed the vehicle.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The vehicle viewed.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Record a view, at most once an hour per renter and vehicle.
     */
    public static function record(User $user, Vehicle $vehicle): void
    {
        $recent = static::where('user_id', $user->id)
            ->where('vehicle_id', $vehicle->id)
            ->where('viewed_at', '>', now()->subHour())
            ->exists();

        if (! $recent) {
            $view = new static;
            $view->forceFill(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'viewed_at' => now()])->save();
        }
    }

    /**
     * Views older than 180 days.
     *
     * @return Builder<VehicleView>
     */
    public function prunable(): Builder
    {
        return static::where('viewed_at', '<', now()->subDays(180));
    }
}
