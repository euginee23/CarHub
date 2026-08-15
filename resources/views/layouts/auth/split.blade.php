{{-- Guest auth shell: form column on the left, brand panel on the right.

     Light-only, matching the marketing site. That means no `dark` class on <html>
     and — critically — `partials.marketing-head` rather than `partials.head`,
     because the latter emits @fluxAppearance, which restores a persisted dark
     preference at runtime and would undo the light shell. --}}
@props([
    'title' => null,
    'description' => null,
    'panelHeading' => null,
    'panelDescription' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.marketing-head', ['title' => $title, 'description' => $description])

        <meta name="robots" content="noindex" />
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased">
        <div class="grid min-h-svh lg:grid-cols-2">
            {{-- Form column --}}
            <div class="relative isolate flex flex-col overflow-hidden">
                <x-marketing.glow class="lg:hidden" />

                <header class="relative p-6 lg:p-10">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-brand-500 shadow-sm shadow-brand-600/30">
                            <x-app-logo-icon class="size-5 text-white" />
                        </span>
                        <span class="text-lg font-bold tracking-tight text-zinc-900 lg:sr-only">{{ config('app.name') }}</span>
                    </a>
                </header>

                <main class="relative flex flex-1 items-center justify-center px-6 pb-12 lg:px-10">
                    <div class="w-full max-w-sm">
                        {{ $slot }}
                    </div>
                </main>

                <footer class="relative px-6 pb-8 lg:px-10">
                    <p class="text-center text-xs text-zinc-500 lg:text-start">
                        &copy; {{ now()->year }} {{ config('app.name') }} &middot;
                        <a href="{{ route('terms') }}" class="underline underline-offset-2 transition-colors hover:text-zinc-700">{{ __('Terms') }}</a>
                    </p>
                </footer>
            </div>

            {{-- Brand column --}}
            <x-auth.brand-panel :heading="$panelHeading" :description="$panelDescription" />
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
