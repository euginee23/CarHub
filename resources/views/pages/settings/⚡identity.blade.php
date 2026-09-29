<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ID verification')] class extends Component {
    //
}; ?>

<div>
    <x-app.page-header :title="__('ID verification')" :description="__('Verify your identity once with two government IDs, and use it for every booking.')" />

    <x-app.content width="3xl">
        <flux:card>
            <livewire:identity.id-documents />
        </flux:card>
    </x-app.content>
</div>
