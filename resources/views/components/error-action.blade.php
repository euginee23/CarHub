@props([
    'href',
    'variant' => 'primary',
])

<a
    href="{{ $href }}"
    {{ $attributes->class([
        'rounded-lg px-5 py-3 text-sm font-semibold transition',
        'bg-brand-600 text-white shadow-sm shadow-brand-600/25 hover:bg-brand-700' => $variant === 'primary',
        'border border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400' => $variant === 'secondary',
    ]) }}
>
    {{ $slot }}
</a>
