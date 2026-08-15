{{-- Presentational only. The assistant is not wired to a backend yet — the input is
     disabled and the replies below are canned. --}}
@php
    $prompts = [
        __('Find me a 7-seater for the weekend'),
        __('What IDs do I need to book?'),
        __('How does payment work?'),
    ];
@endphp

<div x-data="{ open: false }" class="fixed bottom-(--fab-offset) end-5 z-50 flex flex-col items-end gap-3 transition-[bottom]">
    <div
        x-show="open"
        x-cloak
        x-transition.origin.bottom.right
        class="w-[min(22rem,calc(100vw-2.5rem))] overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl shadow-zinc-900/10"
        role="dialog"
        aria-label="{{ __('CarHub assistant') }}"
    >
        <div class="flex items-center gap-3 bg-linear-to-r from-brand-700 to-brand-500 px-4 py-3.5">
            <span class="flex size-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25">
                <svg class="size-4.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.75h4.5M12 3.75V7.5m-4.5 0h9A2.25 2.25 0 0 1 18.75 9.75v6A2.25 2.25 0 0 1 16.5 18h-3l-3 3v-3h-3a2.25 2.25 0 0 1-2.25-2.25v-6A2.25 2.25 0 0 1 7.5 7.5Z" />
                    <circle cx="9.75" cy="12.75" r="1" fill="currentColor" stroke="none" />
                    <circle cx="14.25" cy="12.75" r="1" fill="currentColor" stroke="none" />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-white">{{ __('CarHub Assistant') }}</p>
                <p class="flex items-center gap-1.5 text-xs text-brand-100">
                    <span class="size-1.5 rounded-full bg-spark-300"></span>
                    {{ __('Usually replies instantly') }}
                </p>
            </div>
            <button type="button" x-on:click="open = false" class="rounded-lg p-1.5 text-white/80 transition-colors hover:bg-white/15 hover:text-white">
                <span class="sr-only">{{ __('Close') }}</span>
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="space-y-3 bg-zinc-50 px-4 py-4">
            <div class="max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-sm/6 text-zinc-700 shadow-sm ring-1 ring-black/5">
                {{ __('Hi! Tell me where you are going and how many seats you need, and I will pull up the best matches nearby.') }}
            </div>

            <div class="space-y-2 pt-1">
                @foreach ($prompts as $prompt)
                    <button
                        type="button"
                        disabled
                        class="block w-full cursor-not-allowed rounded-xl border border-zinc-200 bg-white px-3.5 py-2 text-start text-sm text-zinc-600"
                    >
                        {{ $prompt }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="border-t border-zinc-200 bg-white px-4 py-3">
            <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2">
                <input
                    type="text"
                    disabled
                    placeholder="{{ __('Ask about a vehicle…') }}"
                    class="min-w-0 flex-1 cursor-not-allowed bg-transparent text-sm text-zinc-500 outline-hidden placeholder:text-zinc-400"
                />
                <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-zinc-600">
                    {{ __('Soon') }}
                </span>
            </div>
        </div>
    </div>

    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        class="flex size-14 items-center justify-center rounded-full bg-linear-to-br from-brand-600 to-brand-500 text-white shadow-lg shadow-brand-600/35 transition hover:scale-105 hover:shadow-xl hover:shadow-brand-600/40"
    >
        <span class="sr-only">{{ __('Open the CarHub assistant') }}</span>
        <svg x-show="! open" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.75h4.5M12 3.75V7.5m-4.5 0h9A2.25 2.25 0 0 1 18.75 9.75v6A2.25 2.25 0 0 1 16.5 18h-3l-3 3v-3h-3a2.25 2.25 0 0 1-2.25-2.25v-6A2.25 2.25 0 0 1 7.5 7.5Z" />
            <circle cx="9.75" cy="12.75" r="1" fill="currentColor" stroke="none" />
            <circle cx="14.25" cy="12.75" r="1" fill="currentColor" stroke="none" />
        </svg>
        <svg x-show="open" x-cloak class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
    </button>
</div>
