{{-- The grey content area below a page header, on the same 7xl grid as the public
     pages. `width` narrows the column for forms and detail pages. --}}
@props(['width' => null])

@php
    $column = match ($width) {
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        default => 'max-w-7xl',
    };
@endphp

<div class="bg-zinc-50">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
        <div {{ $attributes->class(['mx-auto w-full', $column]) }}>
            {{ $slot }}
        </div>
    </div>
</div>
