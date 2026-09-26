<?php

namespace App\Models;

use Database\Factories\VehiclePhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $vehicle_id
 * @property string $path
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vehicle $vehicle
 */
#[Fillable(['path', 'position'])]
class VehiclePhoto extends Model
{
    /** @use HasFactory<VehiclePhotoFactory> */
    use HasFactory;

    /**
     * The public storage disk listing photos are kept on.
     */
    public const string DISK = 'public';

    /**
     * The vehicle the photo belongs to.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The public URL of the photo.
     */
    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->path);
    }
}
