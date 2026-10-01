{{-- The header on every page, public and signed-in. Guests get the marketing links;
     signed-in users get their own areas (Renting, Hosting, or Administration) plus
     Browse vehicles, so the way back into the app is always one click away.
     Plain links only, no wire:navigate, so public pages always get a full load. --}}
@php
    $user = auth()->user();
    $appSections = $user ? \App\Support\AppNavigation::sections($user) : [];

    $links = $user
        ? [
            ...\App\Support\AppNavigation::areas($user),
            ['label' => __('Browse vehicles'), 'route' => 'vehicles.index', 'active' => ['vehicles.*']],
        ]
        : [
            ['label' => __('Browse vehicles'), 'route' => 'vehicles.index', 'active' => ['vehicles.*']],
            ['label' => __('How it works'), 'route' => 'how-it-works', 'active' => ['how-it-works']],
            ['label' => __('About'), 'route' => 'about', 'active' => ['about']],
            ['label' => __('Contact'), 'route' => 'contact', 'active' => ['contact']],
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
                        'bg-brand-50 text-brand-700' => request()->routeIs(...$link['active']),
                        'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' => ! request()->routeIs(...$link['active']),
                    ])
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        <div class="ms-auto flex items-center gap-2">
            @auth
                {{-- Account menu: every area of the app the user can reach. --}}
                <div x-data="{ menu: false }" x-on:keydown.escape.window="menu = false" class="relative hidden lg:block">
                    <button
                        type="button"
                        x-on:click="menu = ! menu"
                        x-bind:aria-expanded="menu ? 'true' : 'false'"
                        aria-haspopup="true"
                        class="flex items-center gap-2 rounded-lg p-1 pe-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100"
                        data-test="account-menu-button"
                    >
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                            'bg-zinc-900 text-white' => $user->isAdmin(),
                            'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' => $user->isOwner(),
                            'bg-brand-50 text-brand-700 ring-1 ring-brand-200' => $user->isRenter(),
                        ])>{{ $user->role->label() }}</span>
                        <span class="flex size-8 items-center justify-center rounded-full bg-linear-to-br from-brand-600 to-brand-500 text-xs font-semibold text-white">{{ $user->initials() }}</span>
                        <svg class="size-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                        <span class="sr-only">{{ __('Account menu') }}</span>
                    </button>

                    <div
                        x-show="menu"
                        x-cloak
                        x-transition.origin.top.right
                        x-on:click.outside="menu = false"
                        class="absolute end-0 mt-2 w-72 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl shadow-zinc-900/10"
                    >
                        <div class="border-b border-zinc-100 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-zinc-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
                        </div>

                        <div class="max-h-[70vh] overflow-y-auto py-2">
                            @foreach ($appSections as $section)
                                <p class="px-4 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ $section['label'] }}</p>
                                @foreach ($section['links'] as $link)
                                    <a
                                        href="{{ route($link['route']) }}"
                                        @class([
                                            'block px-4 py-2 text-sm',
                                            'bg-brand-50 font-medium text-brand-700' => request()->routeIs($link['active']),
                                            'text-zinc-700 hover:bg-zinc-50' => ! request()->routeIs($link['active']),
                                        ])
                                    >
                                        {{ $link['label'] }}
                                    </a>
                                @endforeach
                            @endforeach
                        </div>

                        <form method="POST" action="{{ route('logout') }}" class="border-t border-zinc-100">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-3 text-start text-sm font-medium text-zinc-700 hover:bg-zinc-50" data-test="logout-button">
                                {{ __('Log out') }}
                            </button>
                        </form>
                    </div>
                </div>
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
        <div class="max-h-[calc(100dvh-4rem)] space-y-1 overflow-y-auto px-4 py-4 sm:px-6">
            {{-- Signed in, every area is listed in full below, so only Browse is repeated here. --}}
            @foreach ($user ? array_slice($links, -1) : $links as $link)
                <a
                    href="{{ route($link['route']) }}"
                    @class([
                        'block rounded-lg px-3 py-2.5 text-base font-medium',
                        'bg-brand-50 text-brand-700' => request()->routeIs(...$link['active']),
                        'text-zinc-700 hover:bg-zinc-100' => ! request()->routeIs(...$link['active']),
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

            @auth
                @foreach ($appSections as $section)
                    <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ $section['label'] }}</p>
                    @foreach ($section['links'] as $link)
                        <a
                            href="{{ route($link['route']) }}"
                            @class([
                                'block rounded-lg px-3 py-2.5 text-base font-medium',
                                'bg-brand-50 text-brand-700' => request()->routeIs($link['active']),
                                'text-zinc-700 hover:bg-zinc-100' => ! request()->routeIs($link['active']),
                            ])
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                @endforeach

                <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-zinc-200 pt-2">
                    @csrf
                    <button type="submit" class="block w-full rounded-lg px-3 py-2.5 text-start text-base font-medium text-zinc-700 hover:bg-zinc-100">
                        {{ __('Log out') }}
                    </button>
                </form>
            @endauth
        </div>
    </div>
</header>
