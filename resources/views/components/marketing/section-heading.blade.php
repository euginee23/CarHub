@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'center',
])

<div @class([
    'max-w-2xl',
    'mx-auto text-center' => $align === 'center',
])>
    @if ($eyebrow)
        <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">{{ $eyebrow }}</p>
    @endif

    <h2 class="mt-3 text-3xl font-bold tracking-tight text-balance text-zinc-900 sm:text-4xl">
        {{ $title }}
    </h2>

    @if ($description)
        <p @class([
            'mt-4 text-lg/8 text-pretty text-zinc-600',
            'mx-auto' => $align === 'center',
        ])>
            {{ $description }}
        </p>
    @endif
</div>
