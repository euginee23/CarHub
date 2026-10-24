<?php

namespace App\Services\Assistant;

use Anthropic\Beta\Messages\BetaTool;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use App\Services\Availability\AvailabilityChecker;
use App\Services\Matching\VehicleSimilarity;
use App\Services\Pricing\RentalQuote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The read-only lookups the assistant may run against live CarHub data. Every
 * result is JSON, so the model reads real listings, availability, and prices
 * instead of guessing them.
 */
class AssistantTools
{
    /**
     * The date-time format the tools accept, in the platform's local time.
     */
    public const string DATE_FORMAT = 'Y-m-d H:i';

    public function __construct(
        protected AvailabilityChecker $availability,
        protected VehicleSimilarity $similarity,
    ) {}

    /**
     * The tool definitions sent to the model.
     *
     * @return list<BetaTool>
     */
    public function definitions(): array
    {
        $dateTime = [
            'type' => 'string',
            'description' => 'Local date and time as "YYYY-MM-DD HH:MM" (24-hour clock).',
        ];

        return [
            BetaTool::with(
                name: 'search_vehicles',
                description: 'Search the vehicles currently listed on CarHub. Body type, minimum seats, maximum daily rate, and trip dates are hard filters; transmission, fuel, and features only rank the results. Pass both pickup_at and return_at to see only vehicles free for the whole trip, each with a price quote. Returns at most 5 vehicles; an empty list means nothing matched, so suggest relaxing a filter.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'body_type' => ['type' => 'string', 'enum' => array_column(VehicleType::cases(), 'value')],
                        'transmission' => ['type' => 'string', 'enum' => array_column(Transmission::cases(), 'value')],
                        'fuel' => ['type' => 'string', 'enum' => array_column(FuelType::cases(), 'value')],
                        'min_seats' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                        'max_price_per_day' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Highest daily rate in Philippine pesos.'],
                        'features' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => Vehicle::FEATURE_OPTIONS]],
                        'pickup_at' => $dateTime,
                        'return_at' => $dateTime,
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                    ],
                    'additionalProperties' => false,
                ],
            ),
            BetaTool::with(
                name: 'check_vehicle',
                description: 'Check whether one vehicle can be booked for a trip and what it would cost, including the service fee. Use the slug from search_vehicles. Returns the scheduling rules the trip breaks, if any.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'pickup_at' => $dateTime,
                        'return_at' => $dateTime,
                    ],
                    'required' => ['slug', 'pickup_at', 'return_at'],
                    'additionalProperties' => false,
                ],
            ),
        ];
    }

    /**
     * Run a tool by name and return its result as JSON.
     *
     * @param  array<string, mixed>  $input
     */
    public function run(string $name, array $input): string
    {
        $result = match ($name) {
            'search_vehicles' => $this->searchVehicles($input),
            'check_vehicle' => $this->checkVehicle($input),
            default => ['error' => "Unknown tool [{$name}]."],
        };

        return (string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Find listed vehicles matching the renter's needs.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function searchVehicles(array $input): array
    {
        $type = VehicleType::tryFrom((string) ($input['body_type'] ?? ''));
        $seats = isset($input['min_seats']) ? max(1, (int) $input['min_seats']) : null;
        $maxPrice = isset($input['max_price_per_day']) ? max(1, (int) $input['max_price_per_day']) : null;
        $limit = min(5, max(1, (int) ($input['limit'] ?? 5)));

        $pickup = $this->parseDateTime($input['pickup_at'] ?? null);
        $return = $this->parseDateTime($input['return_at'] ?? null);

        if (($pickup === null) !== ($return === null)) {
            return ['error' => 'Give both pickup_at and return_at, as "YYYY-MM-DD HH:MM", or neither.'];
        }

        if ($pickup && $return && ($problems = $this->availability->scheduleErrors($pickup, $return)) !== []) {
            return ['schedule_problems' => $problems, 'vehicles' => []];
        }

        $candidates = Vehicle::listed()
            ->when($type, fn (Builder $query) => $query->where('type', $type))
            ->when($seats, fn (Builder $query) => $query->seatsAtLeast($seats))
            ->when($maxPrice, fn (Builder $query) => $query->maxPrice($maxPrice))
            ->when($pickup && $return, fn (Builder $query) => $query->availableBetween($pickup, $return));

        $features = array_values(array_intersect((array) ($input['features'] ?? []), Vehicle::FEATURE_OPTIONS));

        $vehicles = $this->similarity->bestMatchesAmong([
            'type' => $type?->value,
            'transmission' => Transmission::tryFrom((string) ($input['transmission'] ?? ''))?->value,
            'fuel' => FuelType::tryFrom((string) ($input['fuel'] ?? ''))?->value,
            'seats' => $seats,
            'maxPrice' => $maxPrice,
            'features' => $features,
        ], $candidates, $limit);

        return [
            'vehicles' => $vehicles->map(fn (Vehicle $vehicle): array => [
                ...$this->describe($vehicle),
                ...($pickup && $return ? ['quote' => $this->quote($vehicle, $pickup, $return)] : []),
            ])->all(),
        ];
    }

    /**
     * Check one vehicle's availability and price for a trip.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function checkVehicle(array $input): array
    {
        $vehicle = Vehicle::listed()->where('slug', (string) ($input['slug'] ?? ''))->first();

        if (! $vehicle) {
            return ['error' => 'No listed vehicle has that slug. Search again to find it.'];
        }

        $pickup = $this->parseDateTime($input['pickup_at'] ?? null);
        $return = $this->parseDateTime($input['return_at'] ?? null);

        if (! $pickup || ! $return) {
            return ['error' => 'Give pickup_at and return_at as "YYYY-MM-DD HH:MM".'];
        }

        $problems = $this->availability->scheduleErrors($pickup, $return);
        $available = $problems === [] && $this->availability->isAvailable($vehicle, $pickup, $return);

        return [
            'vehicle' => $this->describe($vehicle),
            'available' => $available,
            'schedule_problems' => $problems,
            'quote' => $problems === [] ? $this->quote($vehicle, $pickup, $return) : null,
        ];
    }

    /**
     * The facts about a vehicle the assistant may share.
     *
     * @return array<string, mixed>
     */
    protected function describe(Vehicle $vehicle): array
    {
        return [
            'slug' => $vehicle->slug,
            'name' => $vehicle->name,
            'year' => $vehicle->year,
            'body_type' => $vehicle->type->value,
            'seats' => $vehicle->seats,
            'transmission' => $vehicle->transmission->value,
            'fuel' => $vehicle->fuel->value,
            'price_per_day' => $vehicle->price_per_day,
            'location' => $vehicle->location,
            'rating' => $vehicle->reviews_count > 0 ? $vehicle->rating : null,
            'reviews' => $vehicle->reviews_count,
            'instant_book' => $vehicle->instant_book,
            'features' => $vehicle->features,
            'url' => route('vehicles.show', $vehicle),
        ];
    }

    /**
     * The price of the trip, in pesos.
     *
     * @return array{days: int, subtotal: int, service_fee: int, total: int}
     */
    protected function quote(Vehicle $vehicle, CarbonImmutable $pickup, CarbonImmutable $return): array
    {
        $quote = RentalQuote::for($vehicle, $pickup, $return);

        return [
            'days' => $quote->days,
            'subtotal' => $quote->subtotal,
            'service_fee' => $quote->serviceFee,
            'total' => $quote->total,
        ];
    }

    /**
     * Parse a "YYYY-MM-DD HH:MM" value, or return null when it is missing or malformed.
     */
    protected function parseDateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, trim($value)) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
