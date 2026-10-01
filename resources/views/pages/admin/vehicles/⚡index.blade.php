<?php

use App\Actions\Vehicles\ModerateVehicle;
use App\Enums\BookingStatus;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Vehicles')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /**
     * A listing status, or "taken_down" for moderated listings.
     */
    #[Url(except: '')]
    public string $status = '';

    public ?int $takingDownId = null;

    public string $moderationReason = '';

    /**
     * Every vehicle matching the filters, newest first.
     *
     * @return LengthAwarePaginator<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): LengthAwarePaginator
    {
        return Vehicle::query()
            ->with(['owner', 'coverPhoto'])
            ->withCount([
                'bookings as active_bookings_count' => fn (Builder $query) => $query->whereIn('status', BookingStatus::holding()),
            ])
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('brand', 'like', '%'.trim($this->search).'%')
                ->orWhere('model', 'like', '%'.trim($this->search).'%')
                ->orWhere('location', 'like', '%'.trim($this->search).'%')
                ->orWhereHas('owner', fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->search).'%'))))
            ->when($this->status === 'taken_down', fn (Builder $query) => $query->whereNotNull('moderated_at'))
            ->when(VehicleStatus::tryFrom($this->status), fn (Builder $query, VehicleStatus $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15);
    }

    /**
     * Go back to the first page whenever the filters change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Open the take-down dialog for a listing.
     */
    public function startTakingDown(int $vehicleId): void
    {
        $this->takingDownId = $vehicleId;
        $this->moderationReason = '';

        Flux::modal('take-down-vehicle')->show();
    }

    /**
     * Take the listing open in the dialog off the marketplace.
     */
    public function takeDown(ModerateVehicle $moderateVehicle): void
    {
        $this->validate(['moderationReason' => ['required', 'string', 'min:5', 'max:500']]);

        $vehicle = Vehicle::findOrFail($this->takingDownId);
        $moderateVehicle->takeDown($vehicle, Auth::user(), $this->moderationReason);

        $this->reset(['takingDownId', 'moderationReason']);
        unset($this->vehicles);

        Flux::modal('take-down-vehicle')->close();
        Flux::toast(variant: 'success', text: __('Listing taken down. The owner has been told why.'));
    }

    /**
     * Let the owner list the vehicle again.
     */
    public function allowRelisting(int $vehicleId, ModerateVehicle $moderateVehicle): void
    {
        $moderateVehicle->allowRelisting(Vehicle::findOrFail($vehicleId), Auth::user());

        unset($this->vehicles);

        Flux::toast(variant: 'success', text: __('The owner can list this vehicle again.'));
    }
}; ?>

<div>
    <x-app.page-header :title="__('Vehicles')" :description="__('Monitor every listing on CarHub and take down any that break the rules.')" />

    <x-app.content class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-[1fr_14rem]">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by vehicle, area, or owner')" clearable />
            <flux:select wire:model.live="status">
                <flux:select.option value="">{{ __('All listings') }}</flux:select.option>
                @foreach (VehicleStatus::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
                <flux:select.option value="taken_down">{{ __('Taken down') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white px-4 sm:px-6">
            <flux:table :paginate="$this->vehicles">
                <flux:table.columns>
                    <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                    <flux:table.column>{{ __('Owner') }}</flux:table.column>
                    <flux:table.column>{{ __('Rate') }}</flux:table.column>
                    <flux:table.column>{{ __('Activity') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->vehicles as $vehicle)
                        <flux:table.row :key="$vehicle->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <x-marketing.vehicle-image :vehicle="$vehicle" :caption="false" class="aspect-[4/3] !w-14 rounded-md" />
                                    <div>
                                        <div class="font-medium text-zinc-900">{{ $vehicle->year }} {{ $vehicle->name }}</div>
                                        <div class="text-xs text-zinc-500">{{ $vehicle->type->label() }} &middot; {{ $vehicle->location }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="text-zinc-900">{{ $vehicle->owner->name }}</div>
                                @if ($vehicle->owner->isSuspended())
                                    <flux:badge size="sm" color="red">{{ __('Owner suspended') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>&#8369;{{ number_format($vehicle->price_per_day) }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-600">
                                {{ number_format($vehicle->rating, 1) }}&#9733; &middot; {{ trans_choice('{0} no active bookings|{1} :count active|[2,*] :count active', $vehicle->active_bookings_count, ['count' => $vehicle->active_bookings_count]) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$vehicle->status->color()">{{ $vehicle->status->label() }}</flux:badge>
                                @if ($vehicle->isTakenDown())
                                    <div class="mt-1 max-w-48 text-xs text-red-600">{{ __('Taken down: :reason', ['reason' => $vehicle->moderation_reason]) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    @if ($vehicle->isListed())
                                        <flux:button size="sm" :href="route('vehicles.show', $vehicle)" target="_blank" icon="arrow-top-right-on-square" :aria-label="__('View listing')" />
                                    @endif
                                    @if ($vehicle->isTakenDown())
                                        <flux:button size="sm" wire:click="allowRelisting({{ $vehicle->id }})">{{ __('Allow relisting') }}</flux:button>
                                    @else
                                        <flux:button size="sm" variant="danger" wire:click="startTakingDown({{ $vehicle->id }})">{{ __('Take down') }}</flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </x-app.content>

    <flux:modal name="take-down-vehicle" class="md:w-md">
        <form wire:submit="takeDown" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Take this listing down?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('It leaves the marketplace and the owner cannot relist it until you allow it. The owner sees your reason. Existing bookings are not affected.') }}</flux:text>
            </div>
            <flux:textarea wire:model="moderationReason" :label="__('Reason')" rows="3" :placeholder="__('e.g. Photos do not match the vehicle.')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Take down') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
