{{-- The white title band at the top of every app page, matching the public pages
     (see Browse vehicles), with the area's tabs above the title. --}}
@props([
    'title',
    'description' => null,
    'back' => null,
    'backLabel' => null,
])

<div {{ $attributes->class('border-b border-zinc-200 bg-white') }}>
    <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
        <x-app.section-nav />

        <div class="flex flex-col gap-6 py-8 sm:flex-row sm:items-end sm:justify-between lg:py-10">
            <div class="min-w-0">
                @if ($back)
                    <a href="{{ $back }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-zinc-500 transition-colors hover:text-brand-700">
                        <span aria-hidden="true">&larr;</span> {{ $backLabel ?? __('Back') }}
                    </a>
                @endif
                <h1 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">{{ $title }}</h1>
                @if ($description)
                    <p class="mt-3 max-w-2xl text-lg/8 text-zinc-600">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>
    </div>
</div>
