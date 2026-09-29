<?php

use App\Models\Vehicle;
use App\Support\CompareList;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::marketing')]
#[Title('Compare vehicles')]
class extends Component {
    /**
     * The vehicles being compared, in the order they were added. Anything that
     * has since been unlisted is quietly dropped.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): Collection
    {
        $ids = app(CompareList::class)->ids();

        return Vehicle::listed()
            ->with(['coverPhoto', 'owner'])
            ->whereKey($ids)
            ->get()
            ->sortBy(fn (Vehicle $vehicle) => array_search($vehicle->id, $ids, true))
            ->values();
    }

    /**
     * The spec rows of the comparison table. `best` marks which values win.
     *
     * @return array<int, array{label: string, values: array<int, string>, best: array<int, bool>}>
     */
    #[Computed]
    public function rows(): array
    {
        $vehicles = $this->vehicles;
        $lowestPrice = $vehicles->min('price_per_day');
        $highestRating = $vehicles->max('rating');
        $mostSeats = $vehicles->max('seats');
        $newest = $vehicles->max('year');
        $winsWhen = fn (callable $test) => $vehicles->map(fn (Vehicle $vehicle) => $vehicles->count() > 1 && $test($vehicle))->all();

        return [
            [
                'label' => __('Daily rate'),
                'values' => $vehicles->map(fn (Vehicle $vehicle) => '₱'.number_format($vehicle->price_per_day))->all(),
                'best' => $winsWhen(fn (Vehicle $vehicle) => $vehicle->price_per_day === $lowestPrice),
            ],
            [
                'label' => __('Rating'),
                'values' => $vehicles->map(fn (Vehicle $vehicle) => number_format($vehicle->rating, 1).' ('.$vehicle->trips_count.' '.__('trips').')')->all(),
                'best' => $winsWhen(fn (Vehicle $vehicle) => $vehicle->rating === $highestRating),
            ],
            [
                'label' => __('Body type'),
                'values' => $vehicles->map(fn (Vehicle $vehicle) => $vehicle->type->label())->all(),
                'best' => [],
            ],
            [
                'label' => __('Seats'),
                'values' => $vehicles->map(fn (Vehicle $vehicle) => (string) $vehicle->seats)->all(),
                'best' => $winsWhen(fn (Vehicle $vehicle) => $vehicle->seats === $mostSeats),
            ],
            [
                'label' => __('Transmission'),
                'values' => $vehicles->map(fn (Vehicle $vehicle) => $vehicle->transmission->label())->all(),
                'best' => [],
            ],
            [
                'label' => __('Fuel'),
                'values' => $vehicles->map(fn (Vehicle $vehicle) => $vehicle->fuel->label())->all(),
                'best' => [],
            ],
            [
                'label' => __('Year'),
                'values' => $vehicles->map(fn (Vehicle $vehicle) => (string) $vehicle->year)->all(),
                'best' => $winsWhen(fn (Vehicle $vehicle) => $vehicle->year === $newest),
            ],
            [
                'label' => __('Pickup area'),
                'values' => $vehicles->map(fn (Vehicle $vehicle) => $vehicle->location)->all(),
                'best' => [],
            ],
            [
                'label' => __('Instant book'),
                'values' => $vehicles->map(fn (Vehicle $vehicle) => $vehicle->instant_book ? __('Yes') : __('No'))->all(),
                'best' => [],
            ],
        ];
    }

    /**
     * Every feature offered by at least one of the compared vehicles.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function features(): array
    {
        return $this->vehicles->flatMap(fn (Vehicle $vehicle) => $vehicle->features)->unique()->sort()->values()->all();
    }

    /**
     * Take a vehicle out of the comparison.
     */
    public function remove(int $vehicleId): void
    {
        app(CompareList::class)->remove($vehicleId);

        unset($this->vehicles, $this->rows, $this->features);
    }
}; ?>

<div class="bg-zinc-50">
    <div class="border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">{{ __('Compare vehicles') }}</h1>
            <p class="mt-3 max-w-2xl text-lg/8 text-zinc-600">{{ __('Line up to :count listings side by side before you book.', ['count' => config('carhub.compare_limit')]) }}</p>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
        @if ($this->vehicles->count() < 2)
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('Pick at least two vehicles to compare') }}</h2>
                <p class="mx-auto mt-2 max-w-sm text-sm/6 text-zinc-600">{{ __('Use the Compare button on any listing while browsing.') }}</p>
                <a href="{{ route('vehicles.index') }}" class="mt-6 inline-block rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">
                    {{ __('Browse vehicles') }}
                </a>
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white">
                <table class="w-full min-w-[40rem] table-fixed text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200">
                            <th scope="col" class="w-36 p-4 text-start align-bottom text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Vehicle') }}</th>
                            @foreach ($this->vehicles as $vehicle)
                                <th scope="col" wire:key="head-{{ $vehicle->id }}" class="p-4 text-start align-top font-normal">
                                    <a href="{{ route('vehicles.show', $vehicle) }}" class="block overflow-hidden rounded-xl">
                                        <x-marketing.vehicle-image :vehicle="$vehicle" class="aspect-[16/10]" />
                                    </a>
                                    <a href="{{ route('vehicles.show', $vehicle) }}" class="mt-3 block font-semibold text-zinc-900 hover:text-brand-700">
                                        {{ $vehicle->year }} {{ $vehicle->name }}
                                    </a>
                                    <button type="button" wire:click="remove({{ $vehicle->id }})" class="mt-1 text-xs font-medium text-zinc-500 hover:text-red-600">
                                        {{ __('Remove') }}
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->rows as $row)
                            <tr wire:key="row-{{ $loop->index }}">
                                <th scope="row" class="p-4 text-start font-medium text-zinc-500">{{ $row['label'] }}</th>
                                @foreach ($row['values'] as $index => $value)
                                    <td @class([
                                        'p-4 text-zinc-900',
                                        'font-semibold text-emerald-700' => $row['best'][$index] ?? false,
                                    ])>
                                        {{ $value }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        @foreach ($this->features as $feature)
                            <tr wire:key="feature-{{ $loop->index }}">
                                <th scope="row" class="p-4 text-start font-medium text-zinc-500">{{ $feature }}</th>
                                @foreach ($this->vehicles as $vehicle)
                                    <td class="p-4">
                                        @if (in_array($feature, $vehicle->features, true))
                                            <svg class="size-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-label="{{ __('Included') }}">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        @else
                                            <span class="text-zinc-300" aria-label="{{ __('Not included') }}">&mdash;</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
