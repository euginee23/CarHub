<?php

use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::marketing')]
#[Title('Contact')]
class extends Component {
    public string $name = '';

    public string $email = '';

    public string $subject = '';

    public string $message = '';

    /**
     * Validation rules for the enquiry form.
     *
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email:rfc|max:255',
            'subject' => 'required|string|in:booking,hosting,payment,verification,research,other',
            'message' => 'required|string|min:20|max:2000',
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'subject.in' => __('Please choose what your message is about.'),
            'message.min' => __('Please give us a little more detail — at least 20 characters.'),
        ];
    }

    /**
     * Validate the enquiry and acknowledge it.
     *
     * No mailer is configured for this prototype, so the message is validated and
     * acknowledged rather than delivered.
     */
    public function send(): void
    {
        $this->validate();

        $this->reset(['name', 'email', 'subject', 'message']);

        Flux::toast(
            variant: 'success',
            heading: __('Message received'),
            text: __('Thanks for getting in touch — we will reply within one business day.'),
        );
    }
}; ?>

<div>
    {{-- Header --}}
    <section class="relative isolate overflow-hidden border-b border-zinc-200 bg-white">
        <x-marketing.glow />

        <div class="relative mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8 lg:py-20">
            <h1 class="text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl">{{ __('Get in touch') }}</h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg/8 text-pretty text-zinc-600">
                {{ __('Questions about a booking, hosting a vehicle, or the research behind CarHub — send them here and a human will answer.') }}
            </p>
        </div>
    </section>

    <section class="bg-zinc-50 py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-[1fr_22rem] lg:gap-16">
                {{-- Form --}}
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-zinc-900">{{ __('Send us a message') }}</h2>
                    <p class="mt-2 text-sm/6 text-zinc-600">{{ __('All fields are required. We reply within one business day.') }}</p>

                    <form wire:submit="send" class="mt-8 space-y-6">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <flux:input
                                wire:model="name"
                                :label="__('Your name')"
                                type="text"
                                autocomplete="name"
                                :placeholder="__('Juan dela Cruz')"
                            />

                            <flux:input
                                wire:model="email"
                                :label="__('Email address')"
                                type="email"
                                autocomplete="email"
                                placeholder="you@example.com"
                            />
                        </div>

                        <flux:select wire:model="subject" :label="__('What is this about?')" :placeholder="__('Choose a topic')">
                            <flux:select.option value="booking">{{ __('A booking or trip') }}</flux:select.option>
                            <flux:select.option value="hosting">{{ __('Listing my vehicle') }}</flux:select.option>
                            <flux:select.option value="payment">{{ __('Payments, refunds, or deposits') }}</flux:select.option>
                            <flux:select.option value="verification">{{ __('ID verification') }}</flux:select.option>
                            <flux:select.option value="research">{{ __('The research project') }}</flux:select.option>
                            <flux:select.option value="other">{{ __('Something else') }}</flux:select.option>
                        </flux:select>

                        <flux:textarea
                            wire:model="message"
                            :label="__('Message')"
                            rows="6"
                            :placeholder="__('Tell us what is going on, and include a booking reference if you have one.')"
                        />

                        <div class="flex items-center gap-4">
                            {{-- Flux's primary variant paints from --color-accent (near-black);
                                 the marketing site's CTAs are brand blue. --}}
                            <flux:button variant="primary" type="submit" class="bg-brand-600! hover:bg-brand-700!">
                                {{ __('Send message') }}
                            </flux:button>

                            <span wire:loading wire:target="send" class="text-sm text-zinc-500">{{ __('Sending…') }}</span>
                        </div>
                    </form>
                </div>

                {{-- Support channels --}}
                <div class="space-y-6">
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('Other ways to reach us') }}</h2>

                        <ul class="mt-5 space-y-5">
                            @foreach ([
                                [
                                    'label' => __('Email'),
                                    'value' => 'support@carhub.test',
                                    'meta' => __('Replies within one business day'),
                                    'path' => 'M3.75 6.75h16.5a.75.75 0 0 1 .75.75v9a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1-.75-.75v-9a.75.75 0 0 1 .75-.75Zm.6.6 7.65 5.4 7.65-5.4',
                                ],
                                [
                                    'label' => __('In-app assistant'),
                                    'value' => __('Bottom-right corner'),
                                    'meta' => __('Instant answers to common questions'),
                                    'path' => 'M9.75 3.75h4.5M12 3.75V7.5m-4.5 0h9A2.25 2.25 0 0 1 18.75 9.75v6A2.25 2.25 0 0 1 16.5 18h-3l-3 3v-3h-3a2.25 2.25 0 0 1-2.25-2.25v-6A2.25 2.25 0 0 1 7.5 7.5Z',
                                ],
                                [
                                    'label' => __('Roadside assistance'),
                                    'value' => __('24/7 during active trips'),
                                    'meta' => __('Reachable from your trip page'),
                                    'path' => 'M6.75 3.75h2.25l1.5 4.5-1.875 1.125a12 12 0 0 0 5.25 5.25L15 12.75l4.5 1.5v2.25a1.5 1.5 0 0 1-1.5 1.5A13.5 13.5 0 0 1 5.25 5.25a1.5 1.5 0 0 1 1.5-1.5Z',
                                ],
                            ] as $channel)
                                <li class="flex gap-3.5">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $channel['path'] }}" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-zinc-900">{{ $channel['label'] }}</p>
                                        <p class="mt-0.5 truncate text-sm text-zinc-700">{{ $channel['value'] }}</p>
                                        <p class="mt-0.5 text-xs text-zinc-500">{{ $channel['meta'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-zinc-200 bg-white p-6">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('Where we operate') }}</h2>
                        <p class="mt-2 text-sm/6 text-zinc-600">
                            {{ __('CarHub currently serves Metro Cebu and the surrounding municipalities.') }}
                        </p>
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @foreach (['Cebu City', 'Mandaue City', 'Lapu-Lapu City', 'Talisay City', 'Consolacion', 'Minglanilla'] as $city)
                                <li class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-sm text-zinc-600">{{ $city }}</li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-brand-200 bg-brand-50 p-6">
                        <h2 class="text-lg font-semibold text-brand-900">{{ __('Looking for a quick answer?') }}</h2>
                        <p class="mt-2 text-sm/6 text-brand-900/80">
                            {{ __('Most questions about IDs, payments, deposits, and tracking are already answered in the FAQ.') }}
                        </p>
                        <a href="{{ route('faq') }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-brand-700 transition-colors hover:text-brand-800">
                            {{ __('Read the FAQ') }} <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
