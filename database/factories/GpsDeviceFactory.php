<?php

namespace Database\Factories;

use App\Models\GpsDevice;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GpsDevice>
 */
class GpsDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'label' => 'ESP32 tracker',
            'token_hash' => GpsDevice::hashToken(GpsDevice::newToken()),
        ];
    }

    /**
     * Use a known plain-text token, so tests can authenticate as the device.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => GpsDevice::hashToken($token),
        ]);
    }
}
