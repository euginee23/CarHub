{{-- A renter's rating and feedback. --}}
@props(['review', 'showVehicle' => false])

<div {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white p-5') }}>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-1" aria-label="{{ __(':rating out of 5 stars', ['rating' => $review->rating]) }}">
            @for ($star = 1; $star <= 5; $star++)
                <svg @class(['size-4', 'text-amber-400' => $star <= $review->rating, 'text-zinc-200' => $star > $review->rating]) viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2.5 15 9l7 .8-5.2 4.7 1.5 6.9L12 17.9 5.7 21.4l1.5-6.9L2 9.8 9 9l3-6.5Z" />
                </svg>
            @endfor
        </div>
        <p class="text-xs text-zinc-500">
            {{ $review->reviewer->name }} &middot; {{ $review->created_at?->format('M Y') }}
            @if ($showVehicle)
                &middot; {{ $review->vehicle->name }}
            @endif
        </p>
    </div>
    @if ($review->comment)
        <p class="mt-3 text-sm/6 text-zinc-700">{{ $review->comment }}</p>
    @endif
</div>
