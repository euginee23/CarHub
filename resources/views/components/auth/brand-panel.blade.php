{{-- The right-hand column of the auth split layout. Hidden below lg. --}}
@props([
    'heading' => null,
    'description' => null,
])

<div class="relative isolate hidden overflow-hidden bg-linear-to-br from-brand-700 via-brand-600 to-brand-500 lg:flex lg:flex-col">
    <x-marketing.glow variant="cta" class="opacity-70" />

    <div class="relative p-12">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25">
                <x-app-logo-icon class="size-5 text-white" />
            </span>
            <span class="text-lg font-bold tracking-tight text-white">{{ config('app.name') }}</span>
        </a>
    </div>

    <div class="relative flex flex-1 flex-col justify-center px-12 pb-12">
        <h2 class="max-w-md text-3xl font-bold tracking-tight text-balance text-white">
            {{ $heading ?? __('Rent the right car, matched to you.') }}
        </h2>

        <p class="mt-4 max-w-md text-base/7 text-pretty text-brand-100">
            {{ $description ?? __('CarHub connects renters with verified local vehicle owners across Metro Cebu — booked in minutes, covered end to end.') }}
        </p>

        <ul class="mt-10 space-y-4">
            @foreach ([
                __('Every renter verified with two government-issued IDs'),
                __('Trips GPS-tracked from pickup to return'),
                __('Third-party cover and roadside assistance included'),
            ] as $point)
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25">
                        <svg class="size-3 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </span>
                    <span class="text-sm/6 text-brand-50">{{ $point }}</span>
                </li>
            @endforeach
        </ul>

        <dl class="mt-12 grid grid-cols-3 gap-6 border-t border-white/15 pt-8">
            @foreach ([
                ['value' => count(config('demo.vehicles')).'+', 'label' => __('Vehicles listed')],
                ['value' => '6', 'label' => __('Cities served')],
                ['value' => '4.8/5', 'label' => __('Trip rating')],
            ] as $stat)
                <div>
                    <dt class="sr-only">{{ $stat['label'] }}</dt>
                    <dd>
                        <span class="block text-2xl font-bold tracking-tight text-white">{{ $stat['value'] }}</span>
                        <span class="mt-0.5 block text-xs text-brand-200">{{ $stat['label'] }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
