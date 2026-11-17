{{-- A numbered step on the booking panel: waiting (greyed), active (pulsing), done (checked) or problem. --}}
@props(['number', 'title', 'state' => 'waiting'])

<div {{ $attributes->class('flex items-center gap-3') }}>
    <span @class([
        'relative flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-bold transition duration-300',
        'bg-brand-600 text-white' => $state === 'active',
        'bg-emerald-500 text-white' => $state === 'done',
        'bg-red-500 text-white' => $state === 'problem',
        'bg-zinc-100 text-zinc-400' => $state === 'waiting',
    ])>
        @if ($state === 'active')
            <span class="absolute inset-0 rounded-full bg-brand-400 opacity-50 motion-safe:animate-ping" aria-hidden="true"></span>
        @endif

        @if ($state === 'done')
            <flux:icon.check variant="micro" class="relative size-4 motion-safe:animate-date-pop" />
        @elseif ($state === 'problem')
            <span class="relative">!</span>
        @else
            <span class="relative">{{ $number }}</span>
        @endif
    </span>

    <h3 @class([
        'min-w-0 flex-1 text-base font-semibold',
        'text-zinc-900' => $state !== 'waiting',
        'text-zinc-400' => $state === 'waiting',
    ])>{{ $title }}</h3>

    {{ $slot }}
</div>
