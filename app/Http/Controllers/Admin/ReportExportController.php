<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Reports\RentalReports;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * The reports that can be downloaded, and their CSV headers.
     *
     * @var array<string, array<int, string>>
     */
    public const array REPORTS = [
        'daily' => ['Date', 'Bookings requested', 'Collected (PHP)'],
        'bookings' => ['Reference', 'Requested', 'Status', 'Vehicle', 'Body type', 'Renter', 'Owner', 'Pickup', 'Return', 'Days', 'Rental (PHP)', 'Service fee (PHP)', 'Total (PHP)'],
        'payments' => ['Reference', 'Booking', 'Method', 'Provider', 'Amount (PHP)', 'Paid at'],
        'demand' => ['Date', 'Body type', 'Requests by pickup date'],
    ];

    /**
     * Download one report for the chosen date range as CSV.
     */
    public function __invoke(Request $request, string $report): StreamedResponse
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $reports = new RentalReports(CarbonImmutable::parse($validated['from']), CarbonImmutable::parse($validated['to']));
        $filename = "carhub-{$report}-{$validated['from']}-to-{$validated['to']}.csv";

        return response()->streamDownload(function () use ($report, $reports): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                throw new RuntimeException('Could not open the output stream for the CSV export.');
            }

            fputcsv($out, self::REPORTS[$report]);

            foreach ($this->rows($report, $reports) as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The rows of a report.
     *
     * @return iterable<int, array<int, string|int|float|null>>
     */
    protected function rows(string $report, RentalReports $reports): iterable
    {
        return match ($report) {
            'daily' => (function () use ($reports) {
                $collected = $reports->dailyCollected();

                return collect($reports->dailyBookings())
                    ->map(fn (int $bookings, string $day) => [$day, $bookings, number_format($collected[$day] ?? 0, 2, '.', '')])
                    ->values();
            })(),
            'bookings' => $reports->bookingsCreated()->map(fn (Booking $booking) => [
                $booking->reference,
                $booking->created_at?->toDateTimeString(),
                $booking->status->label(),
                $booking->vehicle->year.' '.$booking->vehicle->name,
                $booking->vehicle->type->label(),
                $booking->renter->name,
                $booking->owner->name,
                $booking->pickup_at->toDateTimeString(),
                $booking->return_at->toDateTimeString(),
                $booking->days,
                $booking->subtotal,
                $booking->service_fee,
                $booking->total,
            ]),
            'payments' => $reports->paidPayments()->map(fn (Payment $payment) => [
                $payment->reference,
                $payment->booking->reference,
                $payment->method->label(),
                $payment->provider,
                number_format($payment->amountInPesos(), 2, '.', ''),
                $payment->paid_at?->toDateTimeString(),
            ]),
            'demand' => collect($reports->dailyDemandByType())
                ->flatMap(fn (array $types, string $day) => collect($types)->map(fn (int $count, string $type) => [$day, $type, $count])->values()),
            default => [],
        };
    }
}
