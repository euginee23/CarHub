<?php

namespace App\Services\Matching;

use App\Models\Vehicle;

/**
 * Encodes a vehicle — or a renter's stated preferences — as a sparse, weighted
 * feature vector so the two can be compared with cosine similarity.
 *
 * Categorical attributes are one-hot encoded. Numeric attributes are bucketed
 * into bands, with half weight spilling into neighbouring bands so that a
 * ₱2,400 car still looks similar to a ₱2,600 one.
 */
final class VehicleFeatureVector
{
    /**
     * How much each attribute group contributes to similarity.
     *
     * @var array<string, float>
     */
    public const array WEIGHTS = [
        'type' => 3.0,
        'price' => 2.5,
        'seats' => 2.0,
        'transmission' => 1.5,
        'fuel' => 0.75,
        'year' => 0.75,
        'feature' => 0.5,
    ];

    /**
     * Upper bounds of the daily-rate bands, in pesos.
     *
     * @var array<int, int>
     */
    public const array PRICE_BANDS = [1500, 2500, 3500, 5000, PHP_INT_MAX];

    /**
     * Upper bounds of the seating bands.
     *
     * @var array<int, int>
     */
    public const array SEAT_BANDS = [4, 5, 8, PHP_INT_MAX];

    /**
     * Upper bounds of the model-year bands.
     *
     * @var array<int, int>
     */
    public const array YEAR_BANDS = [2017, 2019, 2021, 2023, PHP_INT_MAX];

    /**
     * @param  array<string, float>  $components
     */
    private function __construct(public readonly array $components) {}

    /**
     * Build the vector describing a vehicle.
     */
    public static function forVehicle(Vehicle $vehicle): self
    {
        $components = [
            'type:'.$vehicle->type->value => self::WEIGHTS['type'],
            'transmission:'.$vehicle->transmission->value => self::WEIGHTS['transmission'],
            'fuel:'.$vehicle->fuel->value => self::WEIGHTS['fuel'],
            ...self::banded('price', $vehicle->price_per_day, self::PRICE_BANDS),
            ...self::banded('seats', $vehicle->seats, self::SEAT_BANDS),
            ...self::banded('year', $vehicle->year, self::YEAR_BANDS),
        ];

        foreach ($vehicle->features as $feature) {
            $components['feature:'.mb_strtolower($feature)] = self::WEIGHTS['feature'];
        }

        return new self($components);
    }

    /**
     * Build a vector from what a renter has asked for. Attributes they left open
     * contribute nothing, so they neither help nor hurt a candidate.
     *
     * @param  array{type?: string|null, transmission?: string|null, fuel?: string|null, seats?: int|null, maxPrice?: int|null, features?: array<int, string>}  $preferences
     */
    public static function forPreferences(array $preferences): self
    {
        $components = [];

        foreach (['type', 'transmission', 'fuel'] as $attribute) {
            if (filled($preferences[$attribute] ?? null)) {
                $components[$attribute.':'.$preferences[$attribute]] = self::WEIGHTS[$attribute];
            }
        }

        if (filled($preferences['seats'] ?? null)) {
            $components = [...$components, ...self::banded('seats', (int) $preferences['seats'], self::SEAT_BANDS)];
        }

        // A ceiling is a budget, not a target: aim a little under it.
        if (filled($preferences['maxPrice'] ?? null)) {
            $components = [...$components, ...self::banded('price', (int) round($preferences['maxPrice'] * 0.8), self::PRICE_BANDS)];
        }

        foreach ($preferences['features'] ?? [] as $feature) {
            $components['feature:'.mb_strtolower($feature)] = self::WEIGHTS['feature'];
        }

        return new self($components);
    }

    /**
     * Whether the vector carries any signal at all.
     */
    public function isEmpty(): bool
    {
        return $this->components === [];
    }

    /**
     * Cosine similarity with another vector: 1.0 is identical, 0.0 shares nothing.
     */
    public function cosineSimilarity(self $other): float
    {
        $dot = 0.0;

        foreach ($this->components as $key => $value) {
            $dot += $value * ($other->components[$key] ?? 0.0);
        }

        $magnitude = $this->magnitude() * $other->magnitude();

        return $magnitude > 0.0 ? $dot / $magnitude : 0.0;
    }

    /**
     * The Euclidean length of the vector.
     */
    private function magnitude(): float
    {
        return sqrt(array_sum(array_map(fn (float $value): float => $value ** 2, $this->components)));
    }

    /**
     * Encode a number as its band, with half weight on each neighbouring band.
     *
     * @param  array<int, int>  $bands
     * @return array<string, float>
     */
    private static function banded(string $group, int $value, array $bands): array
    {
        $index = 0;

        while ($value > $bands[$index]) {
            $index++;
        }

        $weight = self::WEIGHTS[$group];
        $components = [$group.':'.$index => $weight];

        foreach ([$index - 1, $index + 1] as $neighbour) {
            if (isset($bands[$neighbour])) {
                $components[$group.':'.$neighbour] = $weight / 2;
            }
        }

        return $components;
    }
}
