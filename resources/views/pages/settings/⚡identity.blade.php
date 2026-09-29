<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ID verification')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('ID verification') }}</flux:heading>

    <x-pages::settings.layout :heading="__('ID verification')" :subheading="__('Verify your identity once, and use it for every booking.')">
        <livewire:identity.id-documents />
    </x-pages::settings.layout>
</section>
