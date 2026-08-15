@props(['vehicle'])

<a
    href="{{ route('vehicles.show', $vehicle['slug']) }}"
    class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lg hover:shadow-brand-900/5"
>
    <div class="relative overflow-hidden">
        <x-marketing.vehicle-image :vehicle="$vehicle" class="aspect-[16/10] transition duration-300 group-hover:scale-105" />

        @if ($vehicle['instant_book'])
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
                <h3 class="truncate font-semibold text-zinc-900">{{ $vehicle['name'] }}</h3>
                <p class="mt-0.5 text-sm text-zinc-500">{{ $vehicle['year'] }} &middot; {{ $vehicle['type'] }}</p>
            </div>

            <span class="flex shrink-0 items-center gap-1 text-sm font-medium text-zinc-900">
                <svg class="size-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2.5 15 9l7 .8-5.2 4.7 1.5 6.9L12 17.9 5.7 21.4l1.5-6.9L2 9.8 9 9l3-6.5Z" />
                </svg>
                {{ number_format($vehicle['rating'], 1) }}
            </span>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500">
            <span class="inline-flex items-center gap-1">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.1a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM12 16V9m0 0a3 3 0 1 0-3-3m3 3a3 3 0 0 0 3-3M12 12h5.5a1.5 1.5 0 0 1 1.5 1.5V16" />
                </svg>
                {{ $vehicle['transmission'] }}
            </span>
            <span aria-hidden="true" class="text-zinc-300">&middot;</span>
            <span class="inline-flex items-center gap-1">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 20v-2.5m11 2.5v-2.5M5 17.5h14a1.5 1.5 0 0 0 1.5-1.5v-2a2.5 2.5 0 0 0-2.5-2.5H6a2.5 2.5 0 0 0-2.5 2.5v2A1.5 1.5 0 0 0 5 17.5Zm1.5-6V6a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v5.5" />
                </svg>
                {{ trans_choice('{1} :count seat|[2,*] :count seats', $vehicle['seats'], ['count' => $vehicle['seats']]) }}
            </span>
        </div>

        <p class="mt-3 flex items-center gap-1 text-sm text-zinc-500">
            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" />
                <circle cx="12" cy="10" r="2.5" />
            </svg>
            <span class="truncate">{{ $vehicle['location'] }}</span>
        </p>

        <div class="mt-auto flex items-baseline justify-between gap-2 border-t border-zinc-100 pt-4">
            <p class="text-zinc-900">
                <span class="text-lg font-bold">&#8369;{{ number_format($vehicle['price_per_day']) }}</span>
                <span class="text-sm font-normal text-zinc-500">/{{ __('day') }}</span>
            </p>
            <span class="text-sm font-semibold text-brand-600 transition-colors group-hover:text-brand-700">
                {{ __('View') }} <span aria-hidden="true">&rarr;</span>
            </span>
        </div>
    </div>
</a>
