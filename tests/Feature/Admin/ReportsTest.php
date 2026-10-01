<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\VehicleType;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Reports\RentalReports;
use App\Support\ChartScale;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00'));
    $this->admin = User::factory()->admin()->create();

    $this->suv = Vehicle::factory()->create(['type' => VehicleType::Suv]);
    $this->sedan = Vehicle::factory()->create(['type' => VehicleType::Sedan]);

    // A 2-day SUV rental, paid by GCash: ₱4,000 rental + ₱600 fee.
    $this->paid = Booking::factory()->forVehicle($this->suv)->status(BookingStatus::Completed)
        ->between('2026-10-10 09:00', '2026-10-12 09:00')
        ->create(['subtotal' => 4000, 'service_fee' => 600, 'total' => 4600, 'created_at' => '2026-10-05 10:00']);
    Payment::factory()->paid()->for($this->paid)->create(['amount' => 460000, 'method' => PaymentMethod::Gcash, 'paid_at' => '2026-10-06 10:00']);

    // A cancelled sedan request.
    Booking::factory()->forVehicle($this->sedan)->status(BookingStatus::Cancelled)
        ->between('2026-10-20 09:00', '2026-10-21 09:00')->create(['created_at' => '2026-10-06 11:00']);

    $this->reports = new RentalReports(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));
});

test('the summary adds up what was collected and what it was for', function () {
    expect($this->reports->summary())->toMatchArray([
        'bookings' => 2,
        'collected' => 4600.0,
        'platformRevenue' => 600.0,
        'ownerEarnings' => 4000.0,
        'averageBooking' => 4600.0,
        'cancellationRate' => 50.0,
    ]);
});

test('fleet utilization is rented vehicle-days over available vehicle-days', function () {
    // 2 rented days out of 2 vehicles × 31 days.
    expect($this->reports->summary()['utilization'])->toBe(round(2 / 62 * 100, 1));

    $suv = collect($this->reports->demandByType())->firstWhere('type', VehicleType::Suv);

    expect($suv)->toMatchArray(['vehicles' => 1, 'requests' => 1, 'rentedDays' => 2.0, 'utilization' => round(2 / 31 * 100, 1)]);
});

test('daily series cover every day of the range', function () {
    $daily = $this->reports->dailyBookings();

    expect($daily)->toHaveCount(31)
        ->and($daily['2026-10-05'])->toBe(1)
        ->and($daily['2026-10-07'])->toBe(0)
        ->and($this->reports->dailyCollected()['2026-10-06'])->toBe(4600.0);
});

test('breakdowns by status and payment method', function () {
    $byStatus = collect($this->reports->bookingsByStatus())->mapWithKeys(fn ($row) => [$row['status']->value => $row['count']]);
    $gcash = collect($this->reports->paymentsByMethod())->firstWhere('method', PaymentMethod::Gcash);

    expect($byStatus['completed'])->toBe(1)
        ->and($byStatus['cancelled'])->toBe(1)
        ->and($gcash)->toMatchArray(['count' => 1, 'amount' => 4600.0])
        ->and($this->reports->topVehicles()[0]['vehicle']->is($this->suv))->toBeTrue();
});

test('demand history counts requests per pickup day and body type for forecasting', function () {
    $demand = $this->reports->dailyDemandByType();

    expect($demand['2026-10-10']['SUV'])->toBe(1)
        ->and($demand['2026-10-20']['Sedan'])->toBe(1)
        ->and($demand['2026-10-11']['SUV'])->toBe(0);
});

test('the reports page shows the figures for the chosen range', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.reports')
        ->set('from', '2026-10-01')
        ->set('to', '2026-10-31')
        ->assertSee('₱4,600')
        ->assertSee('Booking requests per day')
        ->assertSee('Demand and use by body type')
        ->assertSee($this->suv->name);
});

test('the range defaults to the last 30 days and presets move it', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.reports')
        ->assertSet('from', '2026-10-02')
        ->assertSet('to', '2026-10-31')
        ->call('applyPreset', '7')
        ->assertSet('from', '2026-10-25');
});

test('a backwards range is rejected', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.reports')
        ->set('from', '2026-10-20')
        ->set('to', '2026-10-01')
        ->assertHasErrors('range');
});

test('reports download as CSV', function (string $report, string $header, string $row) {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.reports.export', ['report' => $report, 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload("carhub-{$report}-2026-10-01-to-2026-10-31.csv");

    expect($response->streamedContent())->toContain($header)->toContain($row);
})->with([
    'daily summary' => ['daily', 'Date,"Bookings requested","Collected (PHP)"', '2026-10-06,1,4600.00'],
    'bookings' => ['bookings', 'Reference,Requested,Status', 'Completed'],
    'payments' => ['payments', 'Reference,Booking,Method', '4600.00'],
    'demand' => ['demand', 'Date,"Body type"', '2026-10-10,SUV,1'],
]);

test('unknown reports and bad ranges are refused', function () {
    $this->actingAs($this->admin)->get(route('admin.reports.export', ['report' => 'secrets', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertNotFound();
    $this->actingAs($this->admin)->get(route('admin.reports.export', ['report' => 'daily', 'from' => '2026-10-31', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
});

test('only administrators see reports', function () {
    $this->actingAs(User::factory()->verifiedOwner()->create())->get(route('admin.reports'))->assertForbidden();
    $this->actingAs(User::factory()->verifiedOwner()->create())->get(route('admin.reports.export', ['report' => 'daily', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertForbidden();
});

test('chart axes use clean round numbers', function () {
    expect(ChartScale::niceMax(7))->toBe(10.0)
        ->and(ChartScale::niceMax(1234))->toBe(2000.0)
        ->and(ChartScale::ticks(4600))->toBe([0.0, 1250.0, 2500.0, 3750.0, 5000.0])
        ->and(ChartScale::compact(12900, '₱'))->toBe('₱12.9K')
        ->and(ChartScale::compact(4_200_000))->toBe('4.2M');
});
