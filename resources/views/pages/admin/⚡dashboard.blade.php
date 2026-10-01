<?php

use App\Enums\ApplicationStatus;
use App\Enums\BookingStatus;
use App\Enums\DocumentStatus;
use App\Enums\VehicleStatus;
use App\Models\Booking;
use App\Models\OwnerApplication;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin overview')] class extends Component {
    /**
     * Platform-wide headline numbers.
     *
     * @return array{users: int, newUsers: int, owners: int, listed: int, vehicles: int, bookingsThisMonth: int, bookingValue: int, pendingApplications: int, pendingIds: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'users' => User::count(),
            'newUsers' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'owners' => User::whereNotNull('owner_verified_at')->count(),
            'listed' => Vehicle::where('status', VehicleStatus::Listed)->count(),
            'vehicles' => Vehicle::count(),
            'bookingsThisMonth' => Booking::where('created_at', '>=', now()->startOfMonth())->count(),
            'bookingValue' => (int) Booking::whereIn('status', [BookingStatus::Confirmed, BookingStatus::Ongoing, BookingStatus::Completed])->sum('total'),
            'pendingApplications' => OwnerApplication::where('status', ApplicationStatus::Pending)->count(),
            'pendingIds' => VerificationDocument::where('documentable_type', (new User)->getMorphClass())->where('status', DocumentStatus::Pending)->count(),
        ];
    }

    /**
     * How many bookings are in each status, in lifecycle order.
     *
     * @return array<int, array{status: BookingStatus, count: int}>
     */
    #[Computed]
    public function bookingsByStatus(): array
    {
        $counts = Booking::toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return array_map(
            fn (BookingStatus $status) => ['status' => $status, 'count' => (int) ($counts[$status->value] ?? 0)],
            BookingStatus::cases(),
        );
    }

    /**
     * The most recent bookings across the platform.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function recentBookings(): Collection
    {
        return Booking::with(['vehicle', 'renter'])->latest()->take(8)->get();
    }

    /**
     * The newest accounts.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function recentUsers(): Collection
    {
        return User::latest()->take(5)->get();
    }
}; ?>

<div>
    <x-dashboard.greeting :subtitle="__('The state of the CarHub marketplace today.')" />

    <x-app.content class="space-y-8">
        @if ($this->stats['pendingApplications'] + $this->stats['pendingIds'] > 0)
            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.stat
                    :label="__('Owner applications to review')"
                    :value="$this->stats['pendingApplications']"
                    :href="route('admin.owner-applications')"
                    :tone="$this->stats['pendingApplications'] > 0 ? 'attention' : 'default'"
                />
                <x-dashboard.stat
                    :label="__('Renter IDs to review')"
                    :value="$this->stats['pendingIds']"
                    :href="route('admin.id-reviews')"
                    :tone="$this->stats['pendingIds'] > 0 ? 'attention' : 'default'"
                />
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-dashboard.stat :label="__('Users')" :value="number_format($this->stats['users'])" :hint="__(':count new this week', ['count' => $this->stats['newUsers']])" />
            <x-dashboard.stat :label="__('Verified owners')" :value="number_format($this->stats['owners'])" />
            <x-dashboard.stat :label="__('Listed vehicles')" :value="number_format($this->stats['listed'])" :hint="__(':count in total', ['count' => $this->stats['vehicles']])" />
            <x-dashboard.stat
                :label="__('Bookings this month')"
                :value="number_format($this->stats['bookingsThisMonth'])"
                :hint="__('₱:value confirmed booking value, all time', ['value' => number_format($this->stats['bookingValue'])])"
            />
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <x-dashboard.panel :title="__('Recent bookings')" :href="route('admin.bookings.index')">
                @if ($this->recentBookings->isEmpty())
                    <p class="py-4 text-center text-sm text-zinc-500">{{ __('No bookings yet.') }}</p>
                @else
                    <div class="-mx-5 -my-5 overflow-x-auto">
                        <table class="w-full min-w-[36rem] text-sm">
                            <thead>
                                <tr class="border-b border-zinc-100 text-start text-xs font-medium uppercase tracking-wide text-zinc-500">
                                    <th scope="col" class="px-5 py-3 text-start">{{ __('Booking') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start">{{ __('Renter') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start">{{ __('Pickup') }}</th>
                                    <th scope="col" class="px-5 py-3 text-end">{{ __('Total') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ($this->recentBookings as $booking)
                                    <tr wire:key="booking-{{ $booking->id }}">
                                        <td class="px-5 py-3">
                                            <a href="{{ route('admin.bookings.show', $booking) }}" wire:navigate class="font-medium text-zinc-900 hover:text-brand-700">{{ $booking->vehicle->name }}</a>
                                            <p class="text-xs text-zinc-500">{{ $booking->reference }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-zinc-600">{{ $booking->renter->name }}</td>
                                        <td class="px-5 py-3 text-zinc-600">{{ $booking->pickup_at->format('M j, Y') }}</td>
                                        <td class="px-5 py-3 text-end font-medium text-zinc-900">&#8369;{{ number_format($booking->total) }}</td>
                                        <td class="px-5 py-3"><flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-dashboard.panel>

            <div class="space-y-6">
                <x-dashboard.panel :title="__('Bookings by status')">
                    <ul class="space-y-2 text-sm">
                        @foreach ($this->bookingsByStatus as $row)
                            <li wire:key="status-{{ $row['status']->value }}" class="flex items-center justify-between gap-3">
                                <flux:badge size="sm" :color="$row['status']->color()">{{ $row['status']->label() }}</flux:badge>
                                <span class="font-semibold text-zinc-900">{{ number_format($row['count']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-dashboard.panel>

                <x-dashboard.panel :title="__('Newest members')">
                    <ul class="space-y-3">
                        @foreach ($this->recentUsers as $member)
                            <li wire:key="member-{{ $member->id }}" class="flex items-center gap-3 text-sm">
                                <flux:avatar size="sm" :name="$member->name" :initials="$member->initials()" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-zinc-900">{{ $member->name }}</p>
                                    <p class="text-xs text-zinc-500">{{ $member->created_at?->diffForHumans() }}</p>
                                </div>
                                <flux:badge size="sm" :color="match (true) { $member->isAdmin() => 'zinc', $member->isOwner() => 'green', default => 'blue' }">{{ $member->role->label() }}</flux:badge>
                            </li>
                        @endforeach
                    </ul>
                </x-dashboard.panel>
            </div>
        </div>
    </x-app.content>
</div>
