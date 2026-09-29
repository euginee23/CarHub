{{-- A titled section of a dashboard, with an optional "see all" link. --}}
@props([
    'title',
    'description' => null,
    'href' => null,
    'linkLabel' => null,
])

<section {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white') }}>
    <header class="flex items-start justify-between gap-4 border-b border-zinc-100 px-5 py-4">
        <div>
            <h2 class="font-semibold text-zinc-900">{{ $title }}</h2>
            @if ($description)
                <p class="mt-0.5 text-sm text-zinc-500">{{ $description }}</p>
            @endif
        </div>
        @if ($href)
            <a href="{{ $href }}" wire:navigate class="shrink-0 text-sm font-semibold text-brand-600 hover:text-brand-700">
                {{ $linkLabel ?? __('See all') }} <span aria-hidden="true">&rarr;</span>
            </a>
        @endif
    </header>

    <div class="p-5">
        {{ $slot }}
    </div>
</section>
