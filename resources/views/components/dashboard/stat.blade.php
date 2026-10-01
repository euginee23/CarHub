{{-- A headline number on a dashboard. --}}
@props([
    'label',
    'value',
    'hint' => null,
    'href' => null,
    'tone' => 'default',
    'size' => 'lg',
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class([
        'block rounded-2xl border bg-white transition',
        'p-5' => $size === 'lg',
        'p-4' => $size === 'sm',
        'border-zinc-200' => $tone === 'default',
        'border-amber-300 ring-1 ring-amber-200' => $tone === 'attention',
        'hover:border-brand-200 hover:shadow-md hover:shadow-brand-900/5' => $href,
    ]) }}
>
    <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
    <p @class([
        'font-bold tracking-tight text-zinc-900',
        'mt-2 text-3xl' => $size === 'lg',
        'mt-1 text-lg' => $size === 'sm',
    ])>{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-zinc-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
