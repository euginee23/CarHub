{{-- Plain links only, no wire:navigate: the authenticated layout hardcodes
     `class="dark"` on <html>, which an SPA body-swap would not undo. --}}
@php
    $links = [
        ['label' => __('Browse vehicles'), 'route' => 'vehicles.index'],
        ['label' => __('How it works'), 'route' => 'how-it-works'],
        ['label' => __('About'), 'route' => 'about'],
        ['label' => __('Contact'), 'route' => 'contact'],
    ];
@endphp

<header
    x-data="{ open: false, scrolled: false }"
    x-on:scroll.window="scrolled = window.scrollY > 8"
    class="sticky top-0 z-40 transition-colors duration-200"
    x-bind:class="scrolled || open ? 'border-b border-zinc-200/80 bg-white/85 backdrop-blur-xl' : 'border-b border-transparent bg-white/60 backdrop-blur-sm'"
>
    <nav class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-6 lg:h-18 lg:px-8" aria-label="{{ __('Main') }}">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-brand-500 shadow-sm shadow-brand-600/30">
                <x-app-logo-icon class="size-5 text-white" />
            </span>
            <span class="text-lg font-bold tracking-tight text-zinc-900">{{ config('app.name') }}</span>
        </a>

        <div class="hidden items-center gap-1 lg:flex">
            @foreach ($links as $link)
                <a
                    href="{{ route($link['route']) }}"
                    @class([
                        'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                        'bg-brand-50 text-brand-700' => request()->routeIs($link['route']),
                        'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' => ! request()->routeIs($link['route']),
                    ])
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        <div class="ms-auto flex items-center gap-2">
            @auth
                <a
                    href="{{ route('dashboard') }}"
                    class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                >
                    {{ __('Dashboard') }}
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="hidden rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 sm:block"
                >
                    {{ __('Log in') }}
                </a>
                <a
                    href="{{ route('register') }}"
                    class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                >
                    {{ __('Get started') }}
                </a>
            @endauth

            <button
                type="button"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                aria-controls="mobile-nav"
                class="-me-1 flex size-10 items-center justify-center rounded-lg text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 lg:hidden"
            >
                <span class="sr-only">{{ __('Toggle navigation') }}</span>
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path x-show="! open" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                    <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </nav>

    <div
        id="mobile-nav"
        x-show="open"
        x-cloak
        x-transition.origin.top
        x-on:click.outside="open = false"
        class="border-t border-zinc-200 bg-white lg:hidden"
    >
        <div class="space-y-1 px-4 py-4 sm:px-6">
            @foreach ($links as $link)
                <a
                    href="{{ route($link['route']) }}"
                    @class([
                        'block rounded-lg px-3 py-2.5 text-base font-medium',
                        'bg-brand-50 text-brand-700' => request()->routeIs($link['route']),
                        'text-zinc-700 hover:bg-zinc-100' => ! request()->routeIs($link['route']),
                    ])
                >
                    {{ $link['label'] }}
                </a>
            @endforeach

            @guest
                <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2.5 text-base font-medium text-zinc-700 hover:bg-zinc-100 sm:hidden">
                    {{ __('Log in') }}
                </a>
            @endguest
        </div>
    </div>
</header>
