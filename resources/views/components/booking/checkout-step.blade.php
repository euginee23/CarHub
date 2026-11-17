{{--
    One checkout step. The step to do now is a big highlighted card; a finished step is a
    green row that opens to show what was agreed; a step waiting on someone else is amber;
    steps still ahead are dimmed rows. Only pass a body (slot) for states that show it.
--}}
@props(['number', 'title', 'state' => 'locked', 'note' => null])

@if ($state === 'current')
    <section id="current-step" {{ $attributes->class('scroll-mt-6 rounded-2xl border-2 border-brand-500 bg-white p-5 shadow-xl shadow-brand-600/10 ring-4 ring-brand-100 motion-safe:animate-step-in sm:p-7') }}>
        <div class="flex items-center gap-3">
            <span class="relative flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-lg font-bold text-white">
                <span class="absolute inset-0 rounded-full bg-brand-400 opacity-40 motion-safe:animate-ping" aria-hidden="true"></span>
                <span class="relative">{{ $number }}</span>
            </span>
            <h2 class="min-w-0 flex-1 text-xl font-bold text-zinc-900 sm:text-2xl">{{ $title }}</h2>
            <span class="hidden shrink-0 items-center gap-1 rounded-full bg-brand-600 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white shadow-sm sm:inline-flex">
                <flux:icon.map-pin variant="micro" class="size-3.5" />
                {{ __('You are here') }}
            </span>
        </div>

        <div class="mt-6">{{ $slot }}</div>
    </section>
@elseif ($state === 'done')
    <section x-data="{ open: false }" {{ $attributes->class('rounded-2xl border border-emerald-200 bg-emerald-50/70 motion-safe:animate-step-in') }}>
        <button type="button" x-on:click="open = ! open" class="flex w-full items-center gap-3 px-5 py-4 text-left" @if ($slot->hasActualContent()) :aria-expanded="open" @else disabled @endif>
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm shadow-emerald-600/30">
                <flux:icon.check variant="mini" class="size-5 motion-safe:animate-date-pop" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-zinc-900">{{ $number }}. {{ $title }}</span>
                @if ($note)
                    <span class="block text-sm text-emerald-700">{{ $note }}</span>
                @endif
            </span>
            @if ($slot->hasActualContent())
                <span class="flex shrink-0 items-center gap-1 text-sm font-medium text-emerald-700">
                    <span x-text="open ? @js(__('Hide')) : @js(__('View'))">{{ __('View') }}</span>
                    <flux:icon.chevron-down variant="micro" class="size-4 transition" ::class="{ 'rotate-180': open }" />
                </span>
            @endif
        </button>

        @if ($slot->hasActualContent())
            <div x-cloak x-show="open" x-collapse>
                <div class="border-t border-emerald-100 px-5 py-4">{{ $slot }}</div>
            </div>
        @endif
    </section>
@elseif ($state === 'waiting')
    <section {{ $attributes->class('rounded-2xl border border-amber-200 bg-amber-50 p-5 motion-safe:animate-step-in sm:p-6') }}>
        <div class="flex items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center gap-0.5 rounded-full bg-amber-400" aria-hidden="true">
                @foreach ([0, 150, 300] as $delay)
                    <span class="size-1.5 rounded-full bg-white motion-safe:animate-bounce" style="animation-delay: {{ $delay }}ms"></span>
                @endforeach
            </span>
            <h2 class="min-w-0 flex-1 text-lg font-bold text-amber-950">{{ $number }}. {{ $title }}</h2>
        </div>

        <div class="mt-4">{{ $slot }}</div>
    </section>
@else
    <section {{ $attributes->class('flex items-center gap-3 rounded-2xl border border-dashed border-zinc-200 bg-white/70 px-5 py-4') }}>
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-400">
            @if ($state === 'locked')
                <flux:icon.lock-closed variant="micro" class="size-4" />
            @else
                <span class="text-sm font-bold">{{ $number }}</span>
            @endif
        </span>
        <span class="min-w-0 flex-1">
            <span class="block font-semibold text-zinc-500">{{ $number }}. {{ $title }}</span>
            @if ($note)
                <span class="block text-sm text-zinc-500">{{ $note }}</span>
            @endif
        </span>
    </section>
@endif
