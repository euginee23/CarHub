{{-- A single-series column chart over time. Columns are thin with a rounded data
     end, hairline gridlines carry the scale, and each column has a hover/focus
     tooltip; the table below holds every value. One series, so no legend: the
     card's heading names what is plotted. --}}
@props([
    'data',
    'prefix' => '',
    'valueLabel' => __('Value'),
])

@php
    use App\Support\ChartScale;

    $ticks = ChartScale::ticks(max(array_values($data) ?: [0]));
    $top = end($ticks) ?: 1;
    $count = count($data);
    $labelEvery = max(1, (int) ceil($count / 6));
    $points = collect($data)->map(fn ($value, $label) => [
        'label' => \Carbon\CarbonImmutable::parse($label)->format('M j'),
        'value' => $value,
        'display' => $prefix !== '' ? $prefix.number_format($value, 2) : number_format($value),
        'height' => $value > 0 ? max(1.5, $value / $top * 100) : 0,
    ])->values();
@endphp

<figure {{ $attributes->class('relative') }} x-data="{ tip: null }">
    <div class="flex">
        <div class="relative h-48 w-14 shrink-0" aria-hidden="true">
            @foreach ($ticks as $tick)
                <span class="absolute end-2 translate-y-1/2 text-xs text-zinc-500 tabular-nums" style="bottom: {{ $tick / $top * 100 }}%">{{ ChartScale::compact($tick, $prefix) }}</span>
            @endforeach
        </div>

        <div class="relative h-48 flex-1">
            @foreach ($ticks as $tick)
                <div @class(['absolute inset-x-0 border-t', 'border-zinc-300' => $tick == 0, 'border-zinc-200/70' => $tick != 0]) style="bottom: {{ $tick / $top * 100 }}%" aria-hidden="true"></div>
            @endforeach

            <div class="absolute inset-0 flex items-end gap-0.5">
                @foreach ($points as $index => $point)
                    <div
                        class="group flex h-full flex-1 cursor-default items-end justify-center outline-none"
                        tabindex="0"
                        role="img"
                        aria-label="{{ $point['label'] }}: {{ $point['display'] }}"
                        x-on:mouseenter="tip = @js($point + ['x' => ($index + 0.5) / max(1, $count) * 100])"
                        x-on:focus="tip = @js($point + ['x' => ($index + 0.5) / max(1, $count) * 100])"
                        x-on:mouseleave="tip = null"
                        x-on:blur="tip = null"
                    >
                        <div class="w-full max-w-6 rounded-t bg-brand-600 transition group-hover:bg-brand-500 group-focus-visible:ring-2 group-focus-visible:ring-brand-300" style="height: {{ $point['height'] }}%"></div>
                    </div>
                @endforeach
            </div>

            <div
                x-show="tip"
                x-cloak
                class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 -translate-y-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs shadow-lg"
                x-bind:style="tip && `left: ${Math.min(90, Math.max(10, tip.x))}%`"
            >
                <p class="font-semibold text-zinc-900 tabular-nums" x-text="tip?.display"></p>
                <p class="text-zinc-500" x-text="tip?.label"></p>
            </div>
        </div>
    </div>

    {{-- Every nth date, centred under its column; the band is tall enough for the labels. --}}
    <div class="relative ms-14 mt-2 h-4" aria-hidden="true">
        @foreach ($points as $index => $point)
            @if ($index % $labelEvery === 0)
                <span class="absolute -translate-x-1/2 whitespace-nowrap text-xs text-zinc-500" style="left: {{ ($index + 0.5) / max(1, $count) * 100 }}%">{{ $point['label'] }}</span>
            @endif
        @endforeach
    </div>

    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-xs font-medium text-zinc-500 hover:text-zinc-900">{{ __('Show as table') }}</summary>
        <div class="mt-2 max-h-56 overflow-y-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-xs text-zinc-500"><th class="py-1 text-start font-medium">{{ __('Day') }}</th><th class="py-1 text-end font-medium">{{ $valueLabel }}</th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($points as $point)
                        <tr><td class="py-1 text-zinc-600">{{ $point['label'] }}</td><td class="py-1 text-end text-zinc-900 tabular-nums">{{ $point['display'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
