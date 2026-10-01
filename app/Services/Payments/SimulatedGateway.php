<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Support\Facades\URL;

/**
 * A stand-in gateway for local development and demos: it "hosts" checkout on a
 * CarHub page where the tester chooses whether the payment succeeds. The page
 * records the outcome on the payment, and retrieveCheckout() reports it back.
 */
class SimulatedGateway implements PaymentGateway
{
    /**
     * The name stored on payments made through this gateway.
     */
    public function name(): string
    {
        return 'simulated';
    }

    /**
     * Point the renter at the local test checkout page.
     */
    public function createCheckout(Payment $payment): CheckoutSession
    {
        return new CheckoutSession(
            id: 'sim_'.$payment->reference,
            url: URL::temporarySignedRoute('payments.simulated.show', now()->addHour(), ['payment' => $payment]),
        );
    }

    /**
     * Report the outcome the tester chose on the test checkout page.
     */
    public function retrieveCheckout(Payment $payment): CheckoutResult
    {
        $paid = data_get($payment->payload, 'simulated_outcome') === 'paid';

        return new CheckoutResult(
            paid: $paid,
            paymentId: $paid ? 'sim_pay_'.$payment->reference : null,
            amount: $paid ? $payment->amount : null,
            currency: $paid ? $payment->currency : null,
            referenceNumber: $payment->booking->reference,
            raw: ['simulated_outcome' => data_get($payment->payload, 'simulated_outcome')],
            failed: data_get($payment->payload, 'simulated_outcome') === 'failed',
        );
    }
}
