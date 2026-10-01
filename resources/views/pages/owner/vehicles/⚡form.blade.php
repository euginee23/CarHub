<?php

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Livewire\Forms\VehicleForm;
use App\Models\Vehicle;
use App\Actions\Tracking\ConnectGpsDevice;
use App\Models\VehicleBlackout;
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

    public string $blackoutStart = '';

    public string $blackoutEnd = '';

    public string $blackoutReason = '';

    /**
     * A freshly issued tracker token, shown once and then forgotten.
     */
    public ?string $issuedTrackerToken = null;

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
     * The upcoming and current date ranges the owner has blocked.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, VehicleBlackout>
     */
    #[Computed]
    public function blackouts(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->vehicle?->blackouts()->whereDate('ends_on', '>=', today())->get()
            ?? new \Illuminate\Database\Eloquent\Collection;
    }

    /**
     * Block a date range so renters cannot book the vehicle for it.
     */
    public function addBlackout(): void
    {
        Gate::authorize('update', $this->vehicle);

        $validated = $this->validate([
            'blackoutStart' => ['required', 'date', 'after_or_equal:today'],
            'blackoutEnd' => ['required', 'date', 'after_or_equal:blackoutStart'],
            'blackoutReason' => ['nullable', 'string', 'max:100'],
        ], attributes: [
            'blackoutStart' => __('start date'),
            'blackoutEnd' => __('end date'),
        ]);

        $this->vehicle->blackouts()->create([
            'starts_on' => $validated['blackoutStart'],
            'ends_on' => $validated['blackoutEnd'],
            'reason' => filled($validated['blackoutReason']) ? $validated['blackoutReason'] : null,
        ]);

        $this->reset(['blackoutStart', 'blackoutEnd', 'blackoutReason']);
        unset($this->blackouts);
    }

    /**
     * Remove a blocked date range.
     */
    public function deleteBlackout(int $blackoutId): void
    {
        Gate::authorize('update', $this->vehicle);

        $this->vehicle->blackouts()->whereKey($blackoutId)->delete();

        unset($this->blackouts);
    }

    /**
     * Pair a GPS tracker with the vehicle, or issue a new token for it.
     */
    public function connectTracker(ConnectGpsDevice $connectGpsDevice): void
    {
        Gate::authorize('update', $this->vehicle);

        $this->issuedTrackerToken = $connectGpsDevice->handle($this->vehicle);
    }

    /**
     * Unpair the vehicle's GPS tracker; its token stops working immediately.
     */
    public function disconnectTracker(): void
    {
        Gate::authorize('update', $this->vehicle);

        $this->vehicle->gpsDevice()->delete();
        $this->vehicle->unsetRelation('gpsDevice');
        $this->issuedTrackerToken = null;
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

        if ($this->vehicle?->isTakenDown() && $validated['status'] === VehicleStatus::Listed->value) {
            $this->addError('form.status', __('An administrator took this listing down. It can be listed again once they allow it.'));

            return;
        }

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

<div>
    <x-app.page-header
        :title="$vehicle ? __('Edit :name', ['name' => $vehicle->name]) : __('List a vehicle')"
        :description="__('Describe the vehicle, set its rate, and pin where renters pick it up.')"
        :back="route('owner.vehicles.index')"
        :back-label="__('My vehicles')"
    >
        @if ($vehicle?->isListed())
            <x-slot:actions>
                <flux:button :href="route('vehicles.show', $vehicle)" icon="arrow-top-right-on-square" target="_blank">{{ __('View listing') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-app.page-header>

    <x-app.content width="4xl" class="space-y-8">
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
        @endif

        @if ($vehicle?->isTakenDown())
            <flux:callout variant="danger" icon="no-symbol" :heading="__('This listing was taken down by an administrator.')">
                <flux:callout.text>{{ __('Reason: :reason', ['reason' => $vehicle->moderation_reason]) }}</flux:callout.text>
                <flux:callout.text>{{ __('You can still edit it, but it stays off the marketplace until CarHub allows it back. Existing bookings are not affected.') }}</flux:callout.text>
            </flux:callout>
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

            @if ($vehicle)
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Unavailable dates') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Block days you need the vehicle yourself or it is in the shop. Renters cannot book across them.') }}</flux:text>
                    </div>

                    @if ($this->blackouts->isNotEmpty())
                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-700">
                            @foreach ($this->blackouts as $blackout)
                                <li wire:key="blackout-{{ $blackout->id }}" class="flex items-center justify-between gap-4 py-2 text-sm">
                                    <span>
                                        <span class="font-medium">{{ $blackout->starts_on->format('M j, Y') }} &ndash; {{ $blackout->ends_on->format('M j, Y') }}</span>
                                        @if ($blackout->reason)
                                            <span class="text-zinc-500">&middot; {{ $blackout->reason }}</span>
                                        @endif
                                    </span>
                                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="deleteBlackout({{ $blackout->id }})" :aria-label="__('Remove blocked dates')" />
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="grid items-end gap-4 sm:grid-cols-[1fr_1fr_2fr_auto]">
                        <flux:input type="date" wire:model="blackoutStart" :label="__('From')" min="{{ now()->toDateString() }}" />
                        <flux:input type="date" wire:model="blackoutEnd" :label="__('Until')" min="{{ now()->toDateString() }}" />
                        <flux:input wire:model="blackoutReason" :label="__('Reason (optional)')" :placeholder="__('e.g. Maintenance')" />
                        <flux:button wire:click="addBlackout">{{ __('Block dates') }}</flux:button>
                    </div>
                </flux:card>
            @endif

            @if ($vehicle)
                <flux:card class="space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">{{ __('GPS tracker') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Pair the ESP tracker fitted to this vehicle. Its position is recorded only while the vehicle is out on a rental.') }}</flux:text>
                        </div>
                        @if ($vehicle->gpsDevice)
                            <flux:badge :color="$vehicle->gpsDevice->isOnline() ? 'green' : 'zinc'">
                                {{ $vehicle->gpsDevice->isOnline() ? __('Online') : ($vehicle->gpsDevice->last_seen_at ? __('Last seen :time', ['time' => $vehicle->gpsDevice->last_seen_at->diffForHumans()]) : __('Never connected')) }}
                            </flux:badge>
                        @endif
                    </div>

                    @if ($issuedTrackerToken)
                        <flux:callout variant="warning" icon="key" :heading="__('Copy this token into the tracker now — it will not be shown again.')">
                            <flux:callout.text>
                                <code class="block break-all rounded bg-white px-2 py-1 font-mono text-xs text-zinc-900" data-test="tracker-token">{{ $issuedTrackerToken }}</code>
                            </flux:callout.text>
                        </flux:callout>

                        <div class="space-y-2 text-sm">
                            <p class="font-medium text-zinc-900">{{ __('Have the tracker send its position like this:') }}</p>
                            <pre class="overflow-x-auto rounded-xl bg-zinc-900 p-4 text-xs text-zinc-100">POST {{ route('api.tracking.pings') }}
Authorization: Bearer {{ $issuedTrackerToken }}
Content-Type: application/json

{"lat": 10.3157, "lng": 123.8854, "speed": 42.5, "heading": 90}</pre>
                            <p class="text-xs text-zinc-500">{{ __('Send one fix every 15–30 seconds. Speed is in km/h and heading in degrees; both are optional. After losing signal, send buffered fixes together as {"pings": [...]} with a recorded_at time on each.') }}</p>
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        @if ($vehicle->gpsDevice)
                            <flux:button wire:click="connectTracker" wire:confirm="{{ __('Issue a new token? The tracker will stop reporting until you update it.') }}" icon="arrow-path">{{ __('New token') }}</flux:button>
                            <flux:button wire:click="disconnectTracker" wire:confirm="{{ __('Disconnect this tracker?') }}" variant="danger">{{ __('Disconnect') }}</flux:button>
                        @else
                            <flux:button wire:click="connectTracker" variant="primary" icon="signal" data-test="connect-tracker">{{ __('Connect a tracker') }}</flux:button>
                        @endif
                    </div>
                </flux:card>
            @endif
    </x-app.content>
</div>
