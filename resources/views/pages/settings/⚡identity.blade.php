<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ID verification')] class extends Component {
    //
}; ?>

<div>
    <x-app.page-header :title="__('ID verification')" :description="__('Upload two government IDs. An administrator approves them before you can rent, and the approval covers every booking.')" />

    <x-app.content width="3xl">
        <flux:card>
            <livewire:identity.id-documents />
        </flux:card>

        @if (auth()->user()->hasVerifiedIdentity())
            <div class="mt-6 flex justify-end">
                <flux:button variant="primary" :href="route('renter.dashboard')" wire:navigate>{{ __('Go to your dashboard') }}</flux:button>
            </div>
        @endif
    </x-app.content>
</div>
