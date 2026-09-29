{{-- Tabs for the pages in the current area of the app (Renting, Hosting, ...). --}}
@php
    $sections = \App\Support\AppNavigation::sections(auth()->user());
    $current = \App\Support\AppNavigation::currentSection($sections);
@endphp

@if ($current)
    <nav aria-label="{{ $sections[$current]['label'] }}" {{ $attributes->class('-mx-1 flex items-center gap-1 overflow-x-auto') }}>
        <span class="me-2 shrink-0 px-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ $sections[$current]['label'] }}</span>
        @foreach ($sections[$current]['links'] as $link)
            <a
                href="{{ route($link['route']) }}"
                wire:navigate
                @if (request()->routeIs($link['active'])) aria-current="page" @endif
                @class([
                    'shrink-0 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    'bg-brand-50 text-brand-700' => request()->routeIs($link['active']),
                    'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' => ! request()->routeIs($link['active']),
                ])
            >
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
@endif
