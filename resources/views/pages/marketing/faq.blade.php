@php
    $categories = config('demo.faqs');
@endphp

<x-layouts::marketing
    :title="__('FAQ')"
    :description="__('Answers to common questions about booking, ID verification, payments, deposits, GPS tracking, and hosting a vehicle on CarHub.')"
>
    {{-- Header --}}
    <section class="relative isolate overflow-hidden border-b border-zinc-200 bg-white">
        <x-marketing.glow />

        <div class="relative mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8 lg:py-20">
            <h1 class="text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl">
                {{ __('Frequently asked questions') }}
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg/8 text-pretty text-zinc-600">
                {{ __('Everything renters and owners ask most often. If yours is not here, the assistant in the corner or our contact form will get you an answer.') }}
            </p>
        </div>
    </section>

    <section class="bg-white py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-[15rem_1fr] lg:gap-16">
                {{-- Category jump list --}}
                <nav aria-label="{{ __('FAQ categories') }}" class="hidden lg:sticky lg:top-24 lg:block lg:h-fit">
                    <p class="text-sm font-semibold text-zinc-900">{{ __('Jump to') }}</p>
                    <ul class="mt-4 space-y-1">
                        @foreach ($categories as $category)
                            <li>
                                <a
                                    href="#{{ \Illuminate\Support\Str::slug($category['category']) }}"
                                    class="block rounded-lg px-3 py-2 text-sm text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900"
                                >
                                    {{ $category['category'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="space-y-14">
                    @foreach ($categories as $category)
                        <section id="{{ \Illuminate\Support\Str::slug($category['category']) }}" class="scroll-mt-24">
                            <h2 class="text-xl font-bold tracking-tight text-zinc-900">{{ $category['category'] }}</h2>

                            <div class="mt-4 rounded-2xl border border-zinc-200 bg-white px-6">
                                @foreach ($category['items'] as $item)
                                    <x-marketing.faq-item :question="$item['question']">
                                        {{ $item['answer'] }}
                                    </x-marketing.faq-item>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-6 sm:p-8">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('Still stuck?') }}</h2>
                        <p class="mt-2 text-sm/6 text-zinc-600">
                            {{ __('Send us the details and we will come back to you within one business day.') }}
                        </p>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <a href="{{ route('contact') }}" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">
                                {{ __('Contact support') }}
                            </a>
                            <a href="{{ route('terms') }}" class="rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400">
                                {{ __('Read the terms') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts::marketing>
