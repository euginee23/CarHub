<?php

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Livewire\Forms\VehicleForm;
use App\Models\Vehicle;
use App\Models\VehiclePhoto;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Vehicle listing')] class extends Component {
    use WithFileUploads;

    public ?Vehicle $vehicle = null;

    public VehicleForm $form;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPhotos = [];

    /**
     * Load the listing being edited, or prepare a blank one.
     */
    public function mount(?Vehicle $vehicle = null): void
    {
        if ($vehicle?->exists) {
            Gate::authorize('update', $vehicle);

            $this->vehicle = $vehicle;
            $this->form->fillFromVehicle($vehicle);

            return;
        }

        Gate::authorize('create', Vehicle::class);
    }

    /**
     * The listing's saved photos, in display order.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, VehiclePhoto>
     */
    #[Computed]
    public function photos(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->vehicle?->photos()->get() ?? new \Illuminate\Database\Eloquent\Collection;
    }

    /**
     * The feature checkboxes, including any custom features already on the listing.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function featureOptions(): array
    {
        return array_values(array_unique([...Vehicle::FEATURE_OPTIONS, ...$this->form->features]));
    }

    /**
     * Create or update the listing and store any newly uploaded photos.
     */
    public function save(): void
    {
        $this->vehicle
            ? Gate::authorize('update', $this->vehicle)
            : Gate::authorize('create', Vehicle::class);

        $validated = $this->form->validate();

        $this->validate([
            'newPhotos' => ['array', 'max:'.max(0, 10 - $this->photos->count())],
            'newPhotos.*' => ['image', 'max:5120'],
        ]);

        $isNew = $this->vehicle === null;

        $vehicle = DB::transaction(function () use ($validated): Vehicle {
            $vehicle = $this->vehicle ?? new Vehicle;
            $vehicle->fill($validated);

            if (! $vehicle->exists) {
                $vehicle->owner()->associate(Auth::user());
            }

            $vehicle->save();

            $position = (int) $vehicle->photos()->max('position');

            foreach ($this->newPhotos as $photo) {
                $vehicle->photos()->create([
                    'path' => $photo->store('vehicles/'.$vehicle->id, VehiclePhoto::DISK),
                    'position' => ++$position,
                ]);
            }

            return $vehicle;
        });

        $this->reset('newPhotos');

        if ($isNew) {
            session()->flash('status', __('Vehicle saved.'));

            $this->redirectRoute('owner.vehicles.edit', ['vehicle' => $vehicle], navigate: true);

            return;
        }

        unset($this->photos);

        Flux::toast(variant: 'success', text: __('Vehicle saved.'));
    }

    /**
     * Move a photo one place earlier or later in the gallery.
     */
    public function movePhoto(int $photoId, string $direction): void
    {
        Gate::authorize('update', $this->vehicle);

        $photos = $this->photos->values();
        $index = $photos->search(fn (VehiclePhoto $photo): bool => $photo->id === $photoId);
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $photos->has($swapWith)) {
            return;
        }

        $reordered = $photos->all();
        [$reordered[$index], $reordered[$swapWith]] = [$reordered[$swapWith], $reordered[$index]];

        foreach (array_values($reordered) as $position => $photo) {
            $photo->update(['position' => $position]);
        }

        unset($this->photos);
    }

    /**
     * Delete a photo from the listing.
     */
    public function deletePhoto(int $photoId): void
    {
        Gate::authorize('update', $this->vehicle);

        $photo = $this->vehicle->photos()->findOrFail($photoId);

        Storage::disk(VehiclePhoto::DISK)->delete($photo->path);
        $photo->delete();

        unset($this->photos);
    }
}; ?>

<div class="mx-auto w-full max-w-4xl space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $vehicle ? __('Edit :name', ['name' => $vehicle->name]) : __('List a vehicle') }}</flux:heading>
            <flux:subheading size="lg" class="mt-2">{{ __('Describe the vehicle, set its rate, and pin where renters pick it up.') }}</flux:subheading>
        </div>

        @if ($vehicle?->isListed())
            <flux:button :href="route('vehicles.show', $vehicle)" icon="arrow-top-right-on-square" target="_blank">{{ __('View listing') }}</flux:button>
        @endif
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-6">
            <flux:heading size="lg">{{ __('Vehicle details') }}</flux:heading>

            <div class="grid gap-6 sm:grid-cols-3">
                <flux:input wire:model="form.brand" :label="__('Brand')" placeholder="Toyota" required />
                <flux:input wire:model="form.model" :label="__('Model')" placeholder="Vios" required />
                <flux:input wire:model="form.year" :label="__('Year')" type="number" required />
            </div>

            <div class="grid gap-6 sm:grid-cols-4">
                <flux:select wire:model="form.type" :label="__('Body type')" :placeholder="__('Choose…')">
                    @foreach (VehicleType::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.transmission" :label="__('Transmission')">
                    @foreach (Transmission::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.fuel" :label="__('Fuel')">
                    @foreach (FuelType::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.seats" :label="__('Seats')" type="number" min="2" max="30" required />
            </div>

            <flux:textarea wire:model="form.description" :label="__('Description')" rows="4" :placeholder="__('Condition, what the vehicle is good for, anything renters should know.')" />

            <flux:checkbox.group wire:model="form.features" :label="__('Features')" class="grid gap-3 sm:grid-cols-3">
                @foreach ($this->featureOptions as $feature)
                    <flux:checkbox wire:key="feature-{{ $loop->index }}" :value="$feature" :label="$feature" />
                @endforeach
            </flux:checkbox.group>
        </flux:card>

        <flux:card class="space-y-6">
            <flux:heading size="lg">{{ __('Pricing & availability') }}</flux:heading>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="form.price_per_day" :label="__('Daily rate (₱)')" type="number" min="500" step="50" required />

                <flux:select wire:model="form.status" :label="__('Listing status')">
                    @foreach (VehicleStatus::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:switch wire:model="form.instant_book" :label="__('Instant book')" :description="__('Approve matching requests automatically instead of reviewing each one.')" />
        </flux:card>

        <flux:card class="space-y-6">
            <flux:heading size="lg">{{ __('Pickup location') }}</flux:heading>

            <flux:input wire:model="form.location" :label="__('Area or city')" placeholder="Cebu City" required />

            <div
                x-data="locationPicker({ latitude: @js($form->latitude), longitude: @js($form->longitude), latProperty: 'form.latitude', lngProperty: 'form.longitude' })"
                class="space-y-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:text>{{ __('Click the map or drag the pin to set the exact pickup point.') }}</flux:text>
                    <flux:button size="sm" icon="map-pin" x-on:click="useMyLocation()">{{ __('Use my location') }}</flux:button>
                </div>

                <div wire:ignore>
                    <div x-ref="map" class="z-0 h-80 w-full rounded-lg border border-zinc-200 dark:border-zinc-700"></div>
                </div>

                <flux:text class="text-xs">
                    @if ($form->latitude !== null && $form->longitude !== null)
                        {{ __('Pinned at :lat, :lng', ['lat' => number_format($form->latitude, 5), 'lng' => number_format($form->longitude, 5)]) }}
                    @else
                        {{ __('No pin yet.') }}
                    @endif
                </flux:text>
                <flux:error name="form.latitude" />
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <flux:heading size="lg">{{ __('Photos') }}</flux:heading>

            @if ($this->photos->isNotEmpty())
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($this->photos as $photo)
                        <div wire:key="photo-{{ $photo->id }}" class="space-y-2">
                            <img src="{{ $photo->url() }}" alt="" class="aspect-[4/3] w-full rounded-lg object-cover" />
                            <div class="flex justify-between gap-1">
                                <flux:button size="xs" icon="arrow-left" wire:click="movePhoto({{ $photo->id }}, 'up')" :disabled="$loop->first" :aria-label="__('Move earlier')" />
                                <flux:button size="xs" icon="trash" variant="danger" wire:click="deletePhoto({{ $photo->id }})" wire:confirm="{{ __('Delete this photo?') }}" :aria-label="__('Delete photo')" />
                                <flux:button size="xs" icon="arrow-right" wire:click="movePhoto({{ $photo->id }}, 'down')" :disabled="$loop->last" :aria-label="__('Move later')" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <flux:input type="file" wire:model="newPhotos" :label="__('Add photos')" :description="__('Up to 10 photos, 5 MB each. The first photo is the cover.')" accept="image/*" multiple />
            <flux:error name="newPhotos.*" />
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('owner.vehicles.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" data-test="save-vehicle">{{ __('Save vehicle') }}</flux:button>
        </div>
    </form>
</div>
