{{-- A single-series horizontal bar chart for comparing categories. Bars are thin
     with a rounded data end and the value at the tip, so every value is
     visible without hovering. --}}
@props([
    'rows',
])

@php
    $max = max(array_column($rows, 'value') ?: [0]) ?: 1;
@endphp

<ul {{ $attributes->class('space-y-3') }}>
    @foreach ($rows as $row)
        <li class="grid grid-cols-[9rem_1fr] items-center gap-3 text-sm sm:grid-cols-[13rem_1fr]">
            <span class="truncate text-zinc-600">{{ $row['label'] }}</span>
            <span class="flex items-center gap-2" role="img" aria-label="{{ $row['label'] }}: {{ $row['display'] }}">
                @if ($row['value'] > 0)
                    <span class="h-4 shrink-0 rounded-e bg-brand-600" style="width: {{ max(1, $row['value'] / $max * 55) }}%"></span>
                @endif
                <span class="shrink-0 text-zinc-900 tabular-nums">{{ $row['display'] }}</span>
            </span>
        </li>
    @endforeach
</ul>
