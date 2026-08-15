{{-- Collapse is done with a grid-rows transition rather than Alpine's x-collapse plugin,
     which is not guaranteed to be bundled on non-Livewire pages. --}}
@props([
    'question',
    'id' => null,
])

@php
    $panelId = $id ?: 'faq-'.\Illuminate\Support\Str::slug($question);
@endphp

<div x-data="{ open: false }" class="border-b border-zinc-200 last:border-b-0">
    <h3>
        <button
            type="button"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            aria-controls="{{ $panelId }}"
            class="flex w-full items-start justify-between gap-6 py-5 text-start"
        >
            <span class="text-base font-semibold text-zinc-900">{{ $question }}</span>
            <span
                class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 transition-transform duration-200"
                x-bind:class="open && 'rotate-45 bg-brand-50 text-brand-600'"
            >
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                </svg>
            </span>
        </button>
    </h3>

    <div
        id="{{ $panelId }}"
        class="grid transition-all duration-200 ease-out grid-rows-[0fr] opacity-0"
        x-bind:class="open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
    >
        <div class="overflow-hidden">
            <div class="pb-5 pe-12 text-sm/7 text-zinc-600">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
