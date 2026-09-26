<?php

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My vehicles')] class extends Component {
    /**
     * The signed-in owner's vehicles, newest first.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): Collection
    {
        return Auth::user()->vehicles()->with('coverPhoto')->latest()->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('My vehicles') }}</flux:heading>
            <flux:subheading size="lg" class="mt-2">{{ __('Manage your listings, rates, and photos.') }}</flux:subheading>
        </div>
        <flux:button :href="route('owner.vehicles.create')" variant="primary" icon="plus" wire:navigate>{{ __('List a vehicle') }}</flux:button>
    </div>

    @if ($this->vehicles->isEmpty())
        <flux:card class="text-center">
            <flux:heading>{{ __('No vehicles yet') }}</flux:heading>
            <flux:text class="mt-2">{{ __('List your first vehicle to start receiving booking requests.') }}</flux:text>
        </flux:card>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                <flux:table.column>{{ __('Daily rate') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->vehicles as $vehicle)
                    <flux:table.row :key="$vehicle->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <x-marketing.vehicle-image :vehicle="$vehicle" class="aspect-[4/3] !w-16 rounded-md" />
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $vehicle->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $vehicle->year }} &middot; {{ $vehicle->type->label() }}</div>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>&#8369;{{ number_format($vehicle->price_per_day) }}</flux:table.cell>
                        <flux:table.cell>{{ $vehicle->location }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$vehicle->status->color()">{{ $vehicle->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" :href="route('owner.vehicles.edit', $vehicle)" wire:navigate>{{ __('Edit') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
