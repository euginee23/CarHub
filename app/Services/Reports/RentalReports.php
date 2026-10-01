<?php

namespace App\Services\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\VehicleType;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * The numbers behind the admin reports and CSV exports, for one date range.
 * Money is reported in pesos. "Collected" means payments that went through in
 * the range; refunded and refund-due payments are left out.
 *
 * The same accumulated history feeds analytics such as demand forecasting
 * (see dailyDemandByType()).
 */
class RentalReports
{
    /**
     * Statuses that count as the vehicle actually being used.
     *
     * @var array<int, BookingStatus>
     */
    public const array USED = [BookingStatus::Confirmed, BookingStatus::Ongoing, BookingStatus::Completed];

    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    /** @var Collection<int, Booking>|null */
    protected ?Collection $created = null;

    /** @var Collection<int, Payment>|null */
    protected ?Collection $paid = null;

    /** @var Collection<int, Booking>|null */
    protected ?Collection $used = null;

    public function __construct(CarbonImmutable $from, CarbonImmutable $to)
    {
        $this->from = $from->startOfDay();
        $this->to = $to->endOfDay();
    }

    /**
     * The number of calendar days the range covers.
     */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to->addSecond()) ?: 1;
    }

    /**
     * Headline figures for the range.
     *
     * @return array{bookings: int, collected: float, platformRevenue: float, ownerEarnings: float, averageBooking: float, cancellationRate: float, utilization: float, newUsers: int}
     */
    public function summary(): array
    {
        $paidBookings = $this->paidBookings();
        $created = $this->bookingsCreated();
        $lost = $created->filter(fn (Booking $booking) => in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Declined, BookingStatus::Expired], true))->count();
        $collected = $this->paidPayments()->sum('amount') / 100;

        return [
            'bookings' => $created->count(),
            'collected' => $collected,
            'platformRevenue' => (float) $paidBookings->sum('service_fee'),
            'ownerEarnings' => (float) $paidBookings->sum('subtotal'),
            'averageBooking' => $paidBookings->isEmpty() ? 0.0 : round($collected / $paidBookings->count(), 2),
            'cancellationRate' => $created->isEmpty() ? 0.0 : round($lost / $created->count() * 100, 1),
            'utilization' => $this->utilization(),
            'newUsers' => User::whereBetween('created_at', [$this->from, $this->to])->count(),
        ];
    }

    /**
     * Bookings requested on each day of the range, zero-filled.
     *
     * @return array<string, int> Keyed by Y-m-d.
     */
    public function dailyBookings(): array
    {
        $counts = $this->bookingsCreated()->countBy(fn (Booking $booking) => $booking->created_at?->toDateString() ?? '');

        return $this->zeroFilled(fn (string $day) => (int) ($counts[$day] ?? 0));
    }

    /**
     * Money collected on each day of the range, in pesos, zero-filled.
     *
     * @return array<string, float> Keyed by Y-m-d.
     */
    public function dailyCollected(): array
    {
        $sums = $this->paidPayments()
            ->groupBy(fn (Payment $payment) => $payment->paid_at?->toDateString() ?? '')
            ->map(fn (Collection $payments) => $payments->sum('amount') / 100);

        return $this->zeroFilled(fn (string $day) => (float) ($sums[$day] ?? 0));
    }

    /**
     * How the range's booking requests ended up, in lifecycle order.
     *
     * @return array<int, array{status: BookingStatus, count: int}>
     */
    public function bookingsByStatus(): array
    {
        $counts = $this->bookingsCreated()->countBy(fn (Booking $booking) => $booking->status->value);

        return collect(BookingStatus::cases())
            ->map(fn (BookingStatus $status) => ['status' => $status, 'count' => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * Money collected per payment method.
     *
     * @return array<int, array{method: PaymentMethod, count: int, amount: float}>
     */
    public function paymentsByMethod(): array
    {
        $byMethod = $this->paidPayments()->groupBy(fn (Payment $payment) => $payment->method->value);

        return collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $method) => [
                'method' => $method,
                'count' => $byMethod->get($method->value)?->count() ?? 0,
                'amount' => ($byMethod->get($method->value)?->sum('amount') ?? 0) / 100,
            ])
            ->all();
    }

    /**
     * Demand and fleet use per body type: requests made, days actually rented,
     * and the share of that type's available vehicle-days that were rented.
     *
     * @return array<int, array{type: VehicleType, vehicles: int, requests: int, rentedDays: float, utilization: float}>
     */
    public function demandByType(): array
    {
        $vehicles = Vehicle::query()->toBase()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $requests = $this->bookingsCreated()->countBy(fn (Booking $booking) => $booking->vehicle->type->value);
        $rentedDays = $this->usedBookings()
            ->groupBy(fn (Booking $booking) => $booking->vehicle->type->value)
            ->map(fn (Collection $bookings) => $bookings->sum(fn (Booking $booking) => $this->daysInRange($booking)));

        return collect(VehicleType::cases())
            ->map(function (VehicleType $type) use ($vehicles, $requests, $rentedDays) {
                $fleet = (int) ($vehicles[$type->value] ?? 0);
                $rented = round((float) ($rentedDays[$type->value] ?? 0), 1);

                return [
                    'type' => $type,
                    'vehicles' => $fleet,
                    'requests' => (int) ($requests[$type->value] ?? 0),
                    'rentedDays' => $rented,
                    'utilization' => $fleet === 0 ? 0.0 : round(min(100, $rented / ($fleet * $this->days()) * 100), 1),
                ];
            })
            ->filter(fn (array $row) => $row['vehicles'] > 0 || $row['requests'] > 0)
            ->values()
            ->all();
    }

    /**
     * The vehicles that earned their owners the most in the range.
     *
     * @return array<int, array{vehicle: Vehicle, bookings: int, earnings: float}>
     */
    public function topVehicles(int $limit = 5): array
    {
        return $this->paidBookings()
            ->groupBy('vehicle_id')
            ->map(fn (Collection $bookings) => [
                'vehicle' => $bookings->first()->vehicle,
                'bookings' => $bookings->count(),
                'earnings' => (float) $bookings->sum('subtotal'),
            ])
            ->sortByDesc('earnings')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Accounts created in the range, by role.
     *
     * @return array<string, int>
     */
    public function newUsersByRole(): array
    {
        $counts = User::whereBetween('created_at', [$this->from, $this->to])->get(['role'])->countBy(fn (User $user) => $user->role->value);

        return collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [$role->label() => (int) ($counts[$role->value] ?? 0)])->all();
    }

    /**
     * Booking requests per pickup day and body type: the demand history that
     * forecasting learns from. Withdrawn requests still count — they were demand.
     *
     * @return array<string, array<string, int>> [Y-m-d => [type => count]]
     */
    public function dailyDemandByType(): array
    {
        $bookings = Booking::query()
            ->with('vehicle:id,type')
            ->whereBetween('pickup_at', [$this->from, $this->to])
            ->get(['id', 'vehicle_id', 'pickup_at']);

        $counts = $bookings->groupBy(fn (Booking $booking) => $booking->pickup_at->toDateString())
            ->map(fn (Collection $day) => $day->countBy(fn (Booking $booking) => $booking->vehicle->type->value));

        return $this->zeroFilled(fn (string $day) => collect(VehicleType::cases())
            ->mapWithKeys(fn (VehicleType $type) => [$type->value => (int) ($counts[$day][$type->value] ?? 0)])
            ->all());
    }

    /**
     * Bookings requested in the range, for exports.
     *
     * @return Collection<int, Booking>
     */
    public function bookingsCreated(): Collection
    {
        return $this->created ??= Booking::query()
            ->with(['vehicle', 'renter', 'owner'])
            ->whereBetween('created_at', [$this->from, $this->to])
            ->oldest()
            ->get();
    }

    /**
     * Payments that went through in the range.
     *
     * @return Collection<int, Payment>
     */
    public function paidPayments(): Collection
    {
        return $this->paid ??= Payment::query()
            ->with('booking')
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$this->from, $this->to])
            ->oldest('paid_at')
            ->get();
    }

    /**
     * The bookings paid for in the range.
     *
     * @return Collection<int, Booking>
     */
    protected function paidBookings(): Collection
    {
        return Booking::query()
            ->with('vehicle')
            ->whereIn('id', $this->paidPayments()->pluck('booking_id')->unique())
            ->get();
    }

    /**
     * Rentals that overlap the range and actually went ahead.
     *
     * @return Collection<int, Booking>
     */
    protected function usedBookings(): Collection
    {
        return $this->used ??= Booking::query()
            ->with('vehicle')
            ->whereIn('status', self::USED)
            ->where('pickup_at', '<', $this->to)
            ->where('return_at', '>', $this->from)
            ->get();
    }

    /**
     * Rented vehicle-days in the range across the whole fleet, as a percentage
     * of the vehicle-days available.
     */
    protected function utilization(): float
    {
        $fleet = Vehicle::count();

        if ($fleet === 0) {
            return 0.0;
        }

        $rented = $this->usedBookings()->sum(fn (Booking $booking) => $this->daysInRange($booking));

        return round(min(100, $rented / ($fleet * $this->days()) * 100), 1);
    }

    /**
     * How many days of a rental fall inside the range.
     */
    protected function daysInRange(Booking $booking): float
    {
        $start = $booking->pickup_at->max($this->from);
        $end = $booking->return_at->min($this->to);

        return max(0, $start->diffInMinutes($end) / 1440);
    }

    /**
     * One entry per day of the range, built by the callback.
     *
     * @template TValue
     *
     * @param  callable(string): TValue  $value
     * @return array<string, TValue>
     */
    protected function zeroFilled(callable $value): array
    {
        $days = [];

        foreach (CarbonPeriod::create($this->from->toDateString(), $this->to->toDateString()) as $day) {
            $days[$day->toDateString()] = $value($day->toDateString());
        }

        return $days;
    }
}
