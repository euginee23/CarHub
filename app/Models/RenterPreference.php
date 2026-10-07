<?php

namespace App\Models;

use App\Enums\Transmission;
use App\Enums\VehicleType;
use Database\Factories\RenterPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a renter tells CarHub they look for. Combined with what they view and
 * book, it drives their personal vehicle recommendations.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $budget_per_day
 * @property Collection<int, VehicleType>|null $vehicle_types
 * @property int|null $seats_min
 * @property Transmission|null $transmission
 * @property string|null $area
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['budget_per_day', 'vehicle_types', 'seats_min', 'transmission', 'area', 'latitude', 'longitude'])]
class RenterPreference extends Model
{
    /** @use HasFactory<RenterPreferenceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget_per_day' => 'integer',
            'vehicle_types' => AsEnumCollection::of(VehicleType::class),
            'seats_min' => 'integer',
            'transmission' => Transmission::class,
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * The renter the preferences belong to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the renter has said anything about what they want.
     */
    public function isEmpty(): bool
    {
        return $this->budget_per_day === null
            && ($this->vehicle_types === null || $this->vehicle_types->isEmpty())
            && $this->seats_min === null
            && $this->transmission === null
            && $this->latitude === null;
    }
}
