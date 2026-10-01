<?php

use App\Actions\Tracking\ConnectGpsDevice;
use App\Actions\Tracking\EndDemoTrip;
use App\Actions\Tracking\StartDemoTrip;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Vehicle;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('GPS tracker test')] class extends Component {
    /**
     * The vehicle being tested.
     */
    #[Url(as: 'vehicle', except: '')]
    public string $vehicleId = '';

    /**
     * The tracker endpoint as seen from this browser's host, so it is correct
     * whether the page is opened via localhost, a LAN IP, or a tunnel.
     */
    #[Locked]
    public string $endpoint = '';

    /**
     * A freshly issued tracker token, shown once.
     */
    public ?string $issuedToken = null;

    /**
     * Only available outside production, and only to administrators.
     */
    public function mount(): void
    {
        abort_unless(config('carhub.tracking.test_page'), 404);
        Gate::authorize('access-admin');

        $this->endpoint = request()->getSchemeAndHttpHost().route('api.tracking.pings', absolute: false);
    }

    /**
     * Every vehicle, flagged with whether it is out on a trip.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): Collection
    {
        return Vehicle::query()
            ->with(['owner', 'gpsDevice'])
            ->withExists(['bookings as on_trip' => fn ($query) => $query->where('status', BookingStatus::Ongoing)])
            ->orderBy('brand')
            ->orderBy('model')
            ->get();
    }

    /**
     * The vehicle being tested, if one is chosen.
     */
    #[Computed]
    public function vehicle(): ?Vehicle
    {
        return $this->vehicles->firstWhere('id', (int) $this->vehicleId);
    }

    /**
     * The selected vehicle's ongoing rental, during which positions are recorded.
     */
    #[Computed]
    public function ongoingBooking(): ?Booking
    {
        return $this->vehicle?->bookings()->with('renter')->where('status', BookingStatus::Ongoing)->first();
    }

    /**
     * Whether the ongoing rental is a demo trip started from this page.
     */
    #[Computed]
    public function isDemoTrip(): bool
    {
        return $this->ongoingBooking !== null && StartDemoTrip::isDemo($this->ongoingBooking);
    }

    /**
     * Whether the page was opened on this computer only, which a device on the
     * network cannot reach.
     */
    #[Computed]
    public function isLocalHost(): bool
    {
        return in_array(parse_url($this->endpoint, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    }

    /**
     * Forget the shown token when switching vehicles.
     */
    public function updatedVehicleId(): void
    {
        $this->issuedToken = null;
    }

    /**
     * Pair a tracker with the vehicle, or issue it a new token.
     */
    public function assignTracker(ConnectGpsDevice $connectGpsDevice): void
    {
        Gate::authorize('access-admin');

        $vehicle = $this->vehicle;
        abort_if($vehicle === null, 404);

        $this->issuedToken = $connectGpsDevice->handle($vehicle, $vehicle->gpsDevice?->label ?? __('Test tracker'));
        unset($this->vehicles, $this->vehicle);

        $this->dispatch('tracker-token-issued', token: $this->issuedToken);
    }

    /**
     * Put the vehicle on a 24-hour demo rental so positions are recorded.
     */
    public function startDemoTrip(StartDemoTrip $startDemoTrip): void
    {
        Gate::authorize('access-admin');

        $vehicle = $this->vehicle;
        abort_if($vehicle === null, 404);

        $startDemoTrip->handle($vehicle, Auth::user());
        unset($this->vehicles, $this->vehicle, $this->ongoingBooking, $this->isDemoTrip);

        Flux::toast(variant: 'success', text: __('Demo trip started. Positions sent now will be recorded.'));
    }

    /**
     * End the vehicle's demo rental; tracking stops.
     */
    public function endDemoTrip(EndDemoTrip $endDemoTrip): void
    {
        Gate::authorize('access-admin');

        $booking = $this->ongoingBooking;
        abort_if($booking === null, 404);

        $endDemoTrip->handle($booking, Auth::user());
        unset($this->vehicles, $this->vehicle, $this->ongoingBooking, $this->isDemoTrip);

        Flux::toast(variant: 'success', text: __('Demo trip ended.'));
    }
}; ?>

<div>
    <x-app.page-header
        :title="__('GPS tracker test')"
        :description="__('Pair an ESP tracker or a phone with a vehicle, send positions, and watch them on the OpenStreetMap map. Available outside production only.')"
        :back="route('admin.dashboard')"
        :back-label="__('Admin overview')"
    />

    <x-app.content class="space-y-6">
        {{-- 1. Vehicle --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('1. Choose the vehicle') }}</flux:heading>
            <flux:select wire:model.live="vehicleId" :label="__('Vehicle the tracker is fitted to')" :placeholder="__('Choose a vehicle…')">
                @foreach ($this->vehicles as $option)
                    <flux:select.option :value="$option->id">
                        {{ $option->year }} {{ $option->name }} — {{ $option->owner->name }}{{ $option->on_trip ? ' · '.__('on a trip') : '' }}{{ $option->gpsDevice ? ' · '.__('tracker paired') : '' }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:card>

        @if ($this->vehicle)
            @php($vehicle = $this->vehicle)

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- 2. Tracker and endpoint --}}
                <flux:card class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <flux:heading size="lg">{{ __('2. Tracker and endpoint') }}</flux:heading>
                        @if ($vehicle->gpsDevice)
                            <flux:badge :color="$vehicle->gpsDevice->isOnline() ? 'green' : 'zinc'">
                                {{ $vehicle->gpsDevice->isOnline() ? __('Online') : ($vehicle->gpsDevice->last_seen_at ? __('Last seen :time', ['time' => $vehicle->gpsDevice->last_seen_at->diffForHumans()]) : __('Never connected')) }}
                            </flux:badge>
                        @else
                            <flux:badge color="zinc">{{ __('No tracker paired') }}</flux:badge>
                        @endif
                    </div>

                    <div class="space-y-1.5" x-data="{ copied: false }">
                        <p class="text-sm font-medium text-zinc-900">{{ __('POST endpoint') }}</p>
                        <div class="flex gap-2">
                            <code class="min-w-0 flex-1 truncate rounded-lg bg-zinc-100 px-3 py-2 font-mono text-xs text-zinc-900" data-test="endpoint">{{ $endpoint }}</code>
                            <flux:button size="sm" x-on:click="navigator.clipboard.writeText(@js($endpoint)); copied = true; setTimeout(() => copied = false, 1500)">
                                <span x-show="! copied">{{ __('Copy') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                            </flux:button>
                        </div>
                    </div>

                    @if ($this->isLocalHost)
                        <flux:callout variant="warning" icon="wifi" :heading="__('Your ESP or phone cannot reach localhost.')">
                            <flux:callout.text>
                                {{ __('Start the server on your network with `php artisan serve --host=0.0.0.0 --port=8001`, then open this page using your computer\'s LAN IP (e.g. http://192.168.1.10:8001/test-track-gps-map) so the endpoint above uses it. For a phone\'s GPS you also need HTTPS: use a tunnel such as ngrok or cloudflared.') }}
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    @if ($issuedToken)
                        <flux:callout variant="warning" icon="key" :heading="__('Tracker token — copy it now, it is shown once.')">
                            <flux:callout.text>
                                <code class="block break-all rounded bg-white px-2 py-1 font-mono text-xs text-zinc-900" data-test="tracker-token">{{ $issuedToken }}</code>
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    @if ($vehicle->gpsDevice)
                        <flux:button wire:click="assignTracker" wire:confirm="{{ __('Issue a new token? The current tracker will stop working until it gets the new one.') }}" icon="key" data-test="assign-tracker">
                            {{ __('Issue a new token') }}
                        </flux:button>
                    @else
                        <flux:button wire:click="assignTracker" variant="primary" icon="key" data-test="assign-tracker">
                            {{ __('Assign a tracker to this vehicle') }}
                        </flux:button>
                    @endif

                    <details class="group rounded-xl border border-zinc-200">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-zinc-900">{{ __('Request format, curl, and ESP32 example') }}</summary>
                        <div class="space-y-3 border-t border-zinc-200 p-4 text-sm">
                            <p class="text-zinc-600">{{ __('Send JSON with the token as a Bearer header. speed (km/h), heading (0–359°), and recorded_at (ISO 8601) are optional. One fix every 5–30 seconds is plenty.') }}</p>
<pre class="overflow-x-auto rounded-xl bg-zinc-900 p-4 text-xs text-zinc-100">curl -X POST {{ $endpoint }} \
  -H "Authorization: Bearer {{ $issuedToken ?? 'gps_YOUR_TOKEN' }}" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"lat": {{ $vehicle->latitude ?? 10.3157 }}, "lng": {{ $vehicle->longitude ?? 123.8854 }}, "speed": 35}'</pre>
<pre class="overflow-x-auto rounded-xl bg-zinc-900 p-4 text-xs text-zinc-100">// ESP32 + GPS module (TinyGPSPlus on Serial2)
#include &lt;WiFi.h&gt;
#include &lt;HTTPClient.h&gt;
#include &lt;TinyGPSPlus.h&gt;

const char* ENDPOINT = "{{ $endpoint }}";
const char* TOKEN = "{{ $issuedToken ?? 'gps_YOUR_TOKEN' }}";
TinyGPSPlus gps;

void sendFix() {
  HTTPClient http;
  http.begin(ENDPOINT);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + TOKEN);
  String body = "{\"lat\":" + String(gps.location.lat(), 7) +
                ",\"lng\":" + String(gps.location.lng(), 7) +
                ",\"speed\":" + String(gps.speed.kmph(), 1) +
                ",\"heading\":" + String((int) gps.course.deg()) + "}";
  int status = http.POST(body);   // 202 = accepted
  Serial.printf("POST %d %s\n", status, http.getString().c_str());
  http.end();
}

// In loop(): feed gps.encode(Serial2.read()) and, every 10 s
// while gps.location.isValid() and WiFi is connected, call sendFix().</pre>
                        </div>
                    </details>
                </flux:card>

                {{-- 3. Rental --}}
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('3. Rental') }}</flux:heading>

                    @if ($this->ongoingBooking)
                        <flux:callout variant="success" icon="check-circle" :heading="__('On a trip — positions are being recorded.')">
                            <flux:callout.text>
                                {{ __('Booking :reference, rented by :renter, due back :date.', ['reference' => $this->ongoingBooking->reference, 'renter' => $this->ongoingBooking->renter->name, 'date' => $this->ongoingBooking->return_at->format('M j, g:i A')]) }}
                            </flux:callout.text>
                        </flux:callout>
                        <div class="flex flex-wrap gap-2">
                            <flux:button :href="route('admin.bookings.show', $this->ongoingBooking)" wire:navigate>{{ __('Open booking') }}</flux:button>
                            @if ($this->isDemoTrip)
                                <flux:button variant="danger" wire:click="endDemoTrip" data-test="end-demo-trip">{{ __('End demo trip') }}</flux:button>
                            @endif
                        </div>
                    @else
                        <flux:callout icon="information-circle" :heading="__('Not on a trip — positions are accepted but not stored.')">
                            <flux:callout.text>{{ __('Renters only consent to tracking during their rental, so CarHub records positions only then. Start a demo trip to put this vehicle out on a 24-hour test rental.') }}</flux:callout.text>
                        </flux:callout>
                        <flux:error name="demo" />
                        <flux:button variant="primary" wire:click="startDemoTrip" icon="play" data-test="start-demo-trip">{{ __('Start demo trip') }}</flux:button>
                    @endif
                </flux:card>
            </div>

            {{-- 4. Map --}}
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('4. Map') }}</flux:heading>
                @if ($this->ongoingBooking)
                    <livewire:tracking.live-map :booking="$this->ongoingBooking" :key="'map-'.$this->ongoingBooking->id" />
                @elseif ($vehicle->latitude !== null)
                    <flux:text>{{ __('Showing the vehicle\'s pickup point. The route appears here once a trip is running and the tracker reports.') }}</flux:text>
                    <div wire:key="pin-{{ $vehicle->id }}" wire:ignore x-data="pickupAreaMap({ latitude: {{ $vehicle->latitude }}, longitude: {{ $vehicle->longitude }}, exact: true })">
                        <div x-ref="map" class="z-0 h-80 w-full overflow-hidden rounded-xl border border-zinc-200"></div>
                    </div>
                @else
                    <flux:text>{{ __('This vehicle has no pickup pin yet.') }}</flux:text>
                @endif
            </flux:card>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- 5. Phone / browser GPS --}}
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('5. Use this phone or browser as the tracker') }}</flux:heading>

                    <div
                        wire:key="sender-{{ $vehicle->id }}"
                        wire:ignore
                        x-data="gpsSender({ endpoint: @js($endpoint), token: @js($issuedToken ?? ''), latitude: @js($vehicle->latitude), longitude: @js($vehicle->longitude) })"
                        x-on:tracker-token-issued.window="token = $event.detail.token"
                        class="space-y-4"
                    >
                        <p x-show="! secure" x-cloak class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {{ __('This page is not on HTTPS, so phones will refuse to share GPS. Open it through an HTTPS tunnel (ngrok, cloudflared) or on localhost. Manual pings below still work.') }}
                        </p>

                        <div>
                            <label class="text-sm font-medium text-zinc-900" for="sender-token">{{ __('Tracker token') }}</label>
                            <input id="sender-token" type="text" x-model="token" placeholder="gps_…" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-xs" />
                            <p class="mt-1 text-xs text-zinc-500">{{ __('Filled in when you assign a tracker here. On a phone, paste the token you copied.') }}</p>
                        </div>

                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="text-sm font-medium text-zinc-900" for="sender-interval">{{ __('Send every') }}</label>
                                <select id="sender-interval" x-model.number="interval" x-bind:disabled="running" class="mt-1 block rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                                    <option value="5">5 s</option>
                                    <option value="10">10 s</option>
                                    <option value="30">30 s</option>
                                </select>
                            </div>
                            <button type="button" x-show="! running" x-on:click="start()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" data-test="start-sending">{{ __('Start sending my location') }}</button>
                            <button type="button" x-show="running" x-cloak x-on:click="stop()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Stop') }}</button>
                        </div>

                        <dl class="grid grid-cols-2 gap-2 rounded-xl bg-zinc-50 p-3 text-xs">
                            <dt class="text-zinc-500">{{ __('Position') }}</dt>
                            <dd class="text-zinc-900" x-text="fix ? `${fix.lat}, ${fix.lng} (±${fix.accuracy} m)` : '—'"></dd>
                            <dt class="text-zinc-500">{{ __('Speed / heading') }}</dt>
                            <dd class="text-zinc-900" x-text="fix ? `${fix.speed ?? '—'} km/h · ${fix.heading ?? '—'}°` : '—'"></dd>
                            <dt class="text-zinc-500">{{ __('Sent') }}</dt>
                            <dd class="text-zinc-900" x-text="sent"></dd>
                            <dt class="text-zinc-500">{{ __('Last response') }}</dt>
                            <dd class="break-all text-zinc-900" x-text="lastStatus ? `${lastStatus} ${lastResponse}` : '—'"></dd>
                        </dl>

                        <p x-show="error" x-cloak x-text="error" class="text-sm text-red-600"></p>

                        <div class="space-y-2 border-t border-zinc-100 pt-4">
                            <p class="text-sm font-medium text-zinc-900">{{ __('Or send one ping by hand') }}</p>
                            <div class="flex flex-wrap items-end gap-2">
                                <input type="number" step="0.0000001" x-model="manualLat" aria-label="{{ __('Latitude') }}" class="w-36 rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                                <input type="number" step="0.0000001" x-model="manualLng" aria-label="{{ __('Longitude') }}" class="w-36 rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                                <button type="button" x-on:click="sendManual()" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">{{ __('Send ping') }}</button>
                            </div>
                        </div>
                    </div>
                </flux:card>

                {{-- 6. Requests --}}
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('6. What the tracker sent') }}</flux:heading>
                    @if ($vehicle->gpsDevice)
                        <livewire:tracking.request-log :device="$vehicle->gpsDevice" :key="'log-'.$vehicle->gpsDevice->id" />
                    @else
                        <flux:text>{{ __('Assign a tracker first; its requests will be listed here.') }}</flux:text>
                    @endif
                </flux:card>
            </div>
        @endif
    </x-app.content>
</div>
