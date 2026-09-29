{{-- `compare` adds a compare toggle that calls the surrounding Livewire component's
     toggleCompare(): null hides it, otherwise 'on', 'off', or 'full'. --}}
@props(['vehicle', 'compare' => null])

<div {{ $attributes->class('relative') }}>
<a
    href="{{ route('vehicles.show', $vehicle) }}"
    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lg hover:shadow-brand-900/5"
>
    <div class="relative overflow-hidden">
        <x-marketing.vehicle-image :vehicle="$vehicle" class="aspect-[16/10] transition duration-300 group-hover:scale-105" />

        @if ($vehicle->instant_book)
            <span class="absolute start-3 top-3 inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-zinc-800 shadow-sm backdrop-blur">
                <svg class="size-3 text-brand-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M13.5 2 4 13.5h6L9.5 22 20 9.5h-6.9L13.5 2Z" />
                </svg>
                {{ __('Instant book') }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="truncate font-semibold text-zinc-900">{{ $vehicle->name }}</h3>
                <p class="mt-0.5 text-sm text-zinc-500">{{ $vehicle->year }} &middot; {{ $vehicle->type->value }}</p>
            </div>

            <span class="flex shrink-0 items-center gap-1 text-sm font-medium text-zinc-900">
                <svg class="size-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2.5 15 9l7 .8-5.2 4.7 1.5 6.9L12 17.9 5.7 21.4l1.5-6.9L2 9.8 9 9l3-6.5Z" />
                </svg>
                {{ number_format($vehicle->rating, 1) }}
            </span>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500">
            <span class="inline-flex items-center gap-1">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.1a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM12 16V9m0 0a3 3 0 1 0-3-3m3 3a3 3 0 0 0 3-3M12 12h5.5a1.5 1.5 0 0 1 1.5 1.5V16" />
                </svg>
                {{ $vehicle->transmission->value }}
            </span>
            <span aria-hidden="true" class="text-zinc-300">&middot;</span>
            <span class="inline-flex items-center gap-1">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 20v-2.5m11 2.5v-2.5M5 17.5h14a1.5 1.5 0 0 0 1.5-1.5v-2a2.5 2.5 0 0 0-2.5-2.5H6a2.5 2.5 0 0 0-2.5 2.5v2A1.5 1.5 0 0 0 5 17.5Zm1.5-6V6a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v5.5" />
                </svg>
                {{ trans_choice('{1} :count seat|[2,*] :count seats', $vehicle->seats, ['count' => $vehicle->seats]) }}
            </span>
        </div>

        <p class="mt-3 flex items-center gap-1 text-sm text-zinc-500">
            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" />
                <circle cx="12" cy="10" r="2.5" />
            </svg>
            <span class="truncate">{{ $vehicle->location }}</span>
            @if ($vehicle->distance_km !== null)
                <span class="shrink-0 text-zinc-400">&middot; {{ __(':km km away', ['km' => number_format($vehicle->distance_km, 1)]) }}</span>
            @endif
        </p>

        <div class="mt-auto flex items-baseline justify-between gap-2 border-t border-zinc-100 pt-4">
            <p class="text-zinc-900">
                <span class="text-lg font-bold">&#8369;{{ number_format($vehicle->price_per_day) }}</span>
                <span class="text-sm font-normal text-zinc-500">/{{ __('day') }}</span>
            </p>
            <span class="text-sm font-semibold text-brand-600 transition-colors group-hover:text-brand-700">
                {{ __('View') }} <span aria-hidden="true">&rarr;</span>
            </span>
        </div>
    </div>
</a>

@if ($compare !== null)
    <button
        type="button"
        wire:click="toggleCompare({{ $vehicle->id }})"
        aria-pressed="{{ $compare === 'on' ? 'true' : 'false' }}"
        @disabled($compare === 'full')
        @class([
            'absolute end-3 top-3 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold shadow-sm backdrop-blur transition',
            'bg-brand-600 text-white hover:bg-brand-700' => $compare === 'on',
            'bg-white/95 text-zinc-800 hover:bg-white' => $compare === 'off',
            'cursor-not-allowed bg-white/80 text-zinc-400' => $compare === 'full',
        ])
        @if ($compare === 'full') title="{{ __('You can compare up to :count vehicles.', ['count' => config('carhub.compare_limit')]) }}" @endif
    >
        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            @if ($compare === 'on')
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            @else
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            @endif
        </svg>
        {{ $compare === 'on' ? __('Comparing') : __('Compare') }}
    </button>
@endif
</div>
