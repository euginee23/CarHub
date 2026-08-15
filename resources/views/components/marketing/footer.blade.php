@php
    $columns = [
        __('Rent') => [
            ['label' => __('Browse vehicles'), 'href' => route('vehicles.index')],
            ['label' => __('How it works'), 'href' => route('how-it-works')],
            ['label' => __('SUVs & vans'), 'href' => route('vehicles.index', ['type' => 'SUV'])],
            ['label' => __('Budget rentals'), 'href' => route('vehicles.index', ['sort' => 'price-asc'])],
        ],
        __('Host') => [
            ['label' => __('List your vehicle'), 'href' => route('register')],
            ['label' => __('Owner guide'), 'href' => route('how-it-works').'#for-owners'],
            ['label' => __('Pricing & payouts'), 'href' => route('faq').'#for-owners'],
        ],
        __('Company') => [
            ['label' => __('About CarHub'), 'href' => route('about')],
            ['label' => __('Contact'), 'href' => route('contact')],
            ['label' => __('FAQ'), 'href' => route('faq')],
        ],
        __('Legal') => [
            ['label' => __('Terms & conditions'), 'href' => route('terms')],
            ['label' => __('Privacy & tracking'), 'href' => route('terms').'#privacy'],
            ['label' => __('Rental contract'), 'href' => route('terms').'#contract'],
        ],
    ];
@endphp

<footer class="border-t border-zinc-200 bg-zinc-50">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-16">
        <div class="grid gap-10 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-brand-500">
                        <x-app-logo-icon class="size-5 text-white" />
                    </span>
                    <span class="text-lg font-bold tracking-tight text-zinc-900">{{ config('app.name') }}</span>
                </a>

                <p class="mt-4 max-w-sm text-sm/6 text-zinc-600">
                    {{ __('An AI-powered vehicle rental marketplace connecting verified owners and renters — smarter matches, fairer prices, safer trips.') }}
                </p>

                <div class="mt-6 flex items-center gap-2">
                    @foreach ([
                        ['label' => 'Facebook', 'path' => 'M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.5-3.91 3.77-3.91 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94Z'],
                        ['label' => 'Instagram', 'path' => 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.8 3.8 0 0 1-1.38-.9 3.8 3.8 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41 1.27-.06 1.65-.07 4.85-.07Zm0 5.68a4.16 4.16 0 1 0 0 8.32 4.16 4.16 0 0 0 0-8.32Zm0 6.86a2.7 2.7 0 1 1 0-5.4 2.7 2.7 0 0 1 0 5.4Zm5.3-7.02a.97.97 0 1 1-1.94 0 .97.97 0 0 1 1.94 0Z'],
                        ['label' => 'X', 'path' => 'M17.53 3h3.05l-6.67 7.62L21.75 21h-6.14l-4.81-6.28L5.3 21H2.25l7.13-8.15L2.25 3h6.3l4.35 5.75L17.53 3Zm-1.07 16.17h1.69L7.62 4.74H5.81l10.65 14.43Z'],
                    ] as $social)
                        <a
                            href="#"
                            class="flex size-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-500 transition-colors hover:border-zinc-300 hover:text-zinc-900"
                        >
                            <span class="sr-only">{{ $social['label'] }}</span>
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="{{ $social['path'] }}" />
                            </svg>
                        </a>
                    @endforeach
                </div>
            </div>

            @foreach ($columns as $heading => $items)
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900">{{ $heading }}</h3>
                    <ul class="mt-4 space-y-3">
                        @foreach ($items as $item)
                            <li>
                                <a href="{{ $item['href'] }}" class="text-sm text-zinc-600 transition-colors hover:text-brand-700">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-12 flex flex-col gap-4 border-t border-zinc-200 pt-8 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-zinc-500">
                &copy; {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
            </p>
            <p class="text-sm text-zinc-500">
                {{ __('CodeHub.Site') }}
            </p>
        </div>
    </div>
</footer>
