<?php

namespace App\Livewire\Forms;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Livewire\Form;

class VehicleForm extends Form
{
    public string $brand = '';

    public string $model = '';

    public string $year = '';

    public string $type = '';

    public string $transmission = 'Automatic';

    public string $fuel = 'Gasoline';

    public string $seats = '5';

    public string $price_per_day = '';

    public string $description = '';

    /** @var array<int, string> */
    public array $features = [];

    public string $location = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $status = 'draft';

    public bool $instant_book = false;

    /**
     * Populate the form from an existing listing.
     */
    public function fillFromVehicle(Vehicle $vehicle): void
    {
        $this->brand = $vehicle->brand;
        $this->model = $vehicle->model;
        $this->year = (string) $vehicle->year;
        $this->type = $vehicle->type->value;
        $this->transmission = $vehicle->transmission->value;
        $this->fuel = $vehicle->fuel->value;
        $this->seats = (string) $vehicle->seats;
        $this->price_per_day = (string) $vehicle->price_per_day;
        $this->description = $vehicle->description;
        $this->features = $vehicle->features;
        $this->location = $vehicle->location;
        $this->latitude = $vehicle->latitude;
        $this->longitude = $vehicle->longitude;
        $this->status = $vehicle->status->value;
        $this->instant_book = $vehicle->instant_book;
    }

    /**
     * Get the validation rules for a vehicle listing.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:50'],
            'year' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'type' => ['required', Rule::enum(VehicleType::class)],
            'transmission' => ['required', Rule::enum(Transmission::class)],
            'fuel' => ['required', Rule::enum(FuelType::class)],
            'seats' => ['required', 'integer', 'min:2', 'max:30'],
            'price_per_day' => ['required', 'integer', 'min:500', 'max:100000'],
            'description' => ['required', 'string', 'min:30', 'max:2000'],
            'features' => ['array'],
            'features.*' => ['string', 'max:50'],
            'location' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::enum(VehicleStatus::class)],
            'instant_book' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'latitude.required' => __('Drop a pin on the map to set the pickup point.'),
        ];
    }
}
