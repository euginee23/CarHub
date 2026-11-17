<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\AbandonPayment;
use App\Actions\Payments\ValidatePayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentCancelController extends Controller
{
    /**
     * Where the gateway sends the renter who backs out of checkout. The checkout is
     * checked once more (it may have been paid in another tab) and otherwise closed,
     * and the renter can start a fresh one while the payment window is open.
     */
    public function __invoke(Payment $payment, ValidatePayment $validatePayment, AbandonPayment $abandonPayment): RedirectResponse
    {
        Gate::authorize('checkout', $payment->booking);

        if ($payment->isPending()) {
            try {
                $payment = $validatePayment->handle($payment);
            } catch (Throwable $exception) {
                Log::warning('Could not check a payment on cancel from checkout.', ['payment' => $payment->reference, 'exception' => $exception->getMessage()]);
            }

            $payment = $abandonPayment->handle($payment);
        }

        $booking = $payment->booking->refresh();

        if ($payment->status === PaymentStatus::Paid) {
            return redirect()->route('trips.show', $booking)->with('status', __('Payment received — your booking is confirmed!'));
        }

        if ($booking->status !== BookingStatus::AwaitingPayment) {
            return redirect()->route('trips.show', $booking);
        }

        return redirect()->route('trips.checkout', $booking)->with('status', __('Payment cancelled — nothing was charged. You can try again until :deadline.', [
            'deadline' => $booking->payment_due_at?->format('M j, g:i A') ?? '—',
        ]));
    }
}
