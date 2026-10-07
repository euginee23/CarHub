<?php

namespace App\Models;

use App\Enums\VehicleType;
use Database\Factories\DemandForecastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Predicted booking requests for one body type on one future day, from the
 * LSTM model (method "lstm") or the seasonal fallback (method "seasonal").
 *
 * @property int $id
 * @property VehicleType $vehicle_type
 * @property Carbon $date
 * @property float $predicted_requests
 * @property string $method
 * @property string $run_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['vehicle_type', 'date', 'predicted_requests', 'method', 'run_id', 'meta'])]
class DemandForecast extends Model
{
    /** @use HasFactory<DemandForecastFactory> */
    use HasFactory;

    public const string METHOD_LSTM = 'lstm';

    public const string METHOD_SEASONAL = 'seasonal';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'date' => 'date',
            'predicted_requests' => 'float',
            'meta' => 'array',
        ];
    }
}
