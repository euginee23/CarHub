<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\ValidatePayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentReturnController extends Controller
{
    /**
     * Where the gateway sends the renter after checkout. The redirect itself
     * proves nothing, so the payment is checked with the gateway right away —
     * the webhook may not have arrived yet.
     */
    public function __invoke(Payment $payment, ValidatePayment $validatePayment): RedirectResponse
    {
        Gate::authorize('checkout', $payment->booking);

        try {
            $payment = $validatePayment->handle($payment);
        } catch (Throwable $exception) {
            Log::warning('Could not check a payment on return from checkout.', ['payment' => $payment->reference, 'exception' => $exception->getMessage()]);
        }

        return match ($payment->status) {
            PaymentStatus::Paid => redirect()->route('trips.show', $payment->booking)
                ->with('status', __('Payment received — your booking is confirmed!')),
            PaymentStatus::Pending => redirect()->route('trips.show', $payment->booking)
                ->with('status', __('We are confirming your payment with the provider. This page will update once it clears.')),
            default => redirect()->route('trips.checkout', $payment->booking)
                ->withErrors(['payment' => $payment->failure_reason ?? __('The payment did not go through. Please try again.')]),
        };
    }
}
