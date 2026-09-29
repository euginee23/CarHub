<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Support\Geo;
use Carbon\CarbonInterface;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $slug
 * @property string $brand
 * @property string $model
 * @property int $year
 * @property VehicleType $type
 * @property Transmission $transmission
 * @property FuelType $fuel
 * @property int $seats
 * @property int $price_per_day
 * @property string $description
 * @property array<int, string> $features
 * @property string $location
 * @property float|null $latitude
 * @property float|null $longitude
 * @property VehicleStatus $status
 * @property bool $instant_book
 * @property bool $featured
 * @property float $rating
 * @property int $trips_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $name
 * @property float|null $distance_km Set on search results that have an origin.
 * @property float|null $similarity Set on content-based matches.
 * @property-read User $owner
 * @property-read Collection<int, VehiclePhoto> $photos
 * @property-read VehiclePhoto|null $coverPhoto
 * @property-read Collection<int, VehicleBlackout> $blackouts
 */
#[Fillable([
    'brand', 'model', 'year', 'type', 'transmission', 'fuel', 'seats', 'price_per_day',
    'description', 'features', 'location', 'latitude', 'longitude', 'status', 'instant_book',
])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * The features owners can tick when describing their vehicle.
     *
     * @var array<int, string>
     */
    public const array FEATURE_OPTIONS = [
        'Air conditioning', 'Bluetooth audio', 'Apple CarPlay', 'Android Auto', 'Dashcam',
        'GPS tracker', 'USB charging', 'Reverse camera', 'Cruise control', 'Roof rack',
        'Child seat', 'Sliding doors',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'features' => '[]',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (Vehicle $vehicle): void {
            $vehicle->slug ??= static::uniqueSlugFor($vehicle);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'type' => VehicleType::class,
            'transmission' => Transmission::class,
            'fuel' => FuelType::class,
            'seats' => 'integer',
            'price_per_day' => 'integer',
            'features' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'status' => VehicleStatus::class,
            'instant_book' => 'boolean',
            'featured' => 'boolean',
            'rating' => 'float',
            'trips_count' => 'integer',
        ];
    }

    /**
     * The display name of the vehicle, e.g. "Toyota Vios".
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->brand.' '.$this->model));
    }

    /**
     * The verified owner listing the vehicle.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The listing photos, in display order.
     *
     * @return HasMany<VehiclePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(VehiclePhoto::class)->orderBy('position');
    }

    /**
     * The first photo, used on cards and as the hero image.
     *
     * @return HasOne<VehiclePhoto, $this>
     */
    public function coverPhoto(): HasOne
    {
        return $this->hasOne(VehiclePhoto::class)->oldestOfMany('position');
    }

    /**
     * The date ranges the owner has blocked the vehicle for.
     *
     * @return HasMany<VehicleBlackout, $this>
     */
    public function blackouts(): HasMany
    {
        return $this->hasMany(VehicleBlackout::class)->orderBy('starts_on');
    }

    /**
     * Only vehicles that are publicly listed for rent.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('status', VehicleStatus::Listed);
    }

    /**
     * Match every word of the term against the name, body type, or location.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        foreach (Str::of($term)->squish()->explode(' ')->filter() as $word) {
            $query->where(function (Builder $query) use ($word): void {
                foreach (['brand', 'model', 'type', 'location'] as $column) {
                    $query->orWhere($column, 'like', '%'.$word.'%');
                }
            });
        }
    }

    /**
     * Only vehicles with at least the given number of seats.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeSeatsAtLeast(Builder $query, int $seats): void
    {
        $query->where('seats', '>=', $seats);
    }

    /**
     * Only vehicles whose daily rate does not exceed the given amount.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeMaxPrice(Builder $query, int $price): void
    {
        $query->where('price_per_day', '<=', $price);
    }

    /**
     * Only vehicles free for the whole of the given rental window.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeAvailableBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereNotIn(
            $this->qualifyColumn('id'),
            VehicleBlackout::query()->overlapping($from->toDateString(), $to->toDateString())->select('vehicle_id'),
        );
    }

    /**
     * Only pinned vehicles inside the box around a point. This is a cheap SQL
     * pre-filter; use distanceFrom() for the exact radius check.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeNear(Builder $query, float $latitude, float $longitude, float $radiusKm): void
    {
        $box = Geo::boundingBox($latitude, $longitude, $radiusKm);

        $query->whereBetween('latitude', [$box['minLat'], $box['maxLat']])
            ->whereBetween('longitude', [$box['minLng'], $box['maxLng']]);
    }

    /**
     * The straight-line distance from a point to the vehicle's pickup pin, in kilometres.
     */
    public function distanceFrom(float $latitude, float $longitude): ?float
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return Geo::distanceInKm($latitude, $longitude, $this->latitude, $this->longitude);
    }

    /**
     * Determine whether the vehicle is publicly listed.
     */
    public function isListed(): bool
    {
        return $this->status === VehicleStatus::Listed;
    }

    /**
     * Build a slug from the vehicle's name and year that no other vehicle uses.
     */
    public static function uniqueSlugFor(Vehicle $vehicle): string
    {
        $base = Str::slug($vehicle->brand.' '.$vehicle->model.' '.$vehicle->year);
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
