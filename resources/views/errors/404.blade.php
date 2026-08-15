<x-layouts::error
    code="404"
    :title="__('We took a wrong turn')"
    :message="__('That page does not exist, or the vehicle you were looking at is no longer listed. It may have been booked or removed by its owner.')"
>
    <x-slot:actions>
        <x-error-action :href="route('vehicles.index')">{{ __('Browse vehicles') }}</x-error-action>
        <x-error-action :href="route('home')" variant="secondary">{{ __('Back to home') }}</x-error-action>
    </x-slot:actions>

    <div class="mt-12 border-t border-zinc-200 pt-8">
        <p class="text-sm font-semibold text-zinc-900">{{ __('Try one of these instead') }}</p>
        <ul class="mt-4 flex flex-wrap items-center justify-center gap-2">
            @foreach ([
                ['label' => __('How it works'), 'href' => route('how-it-works')],
                ['label' => __('FAQ'), 'href' => route('faq')],
                ['label' => __('Contact support'), 'href' => route('contact')],
            ] as $link)
                <li>
                    <a
                        href="{{ $link['href'] }}"
                        class="rounded-full border border-zinc-200 bg-white px-3.5 py-1.5 text-sm text-zinc-600 transition-colors hover:border-brand-300 hover:text-brand-700"
                    >
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts::error>
