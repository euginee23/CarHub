<?php

namespace App\Actions\Payments;

use App\Actions\Bookings\TransitionBooking;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\CheckoutResult;
use Illuminate\Support\Facades\DB;

class ValidatePayment
{
    public function __construct(
        protected ResolvePaymentGateway $gateways,
        protected TransitionBooking $transitions,
    ) {}

    /**
     * Ask the gateway what happened to a payment and act on it: a payment that is
     * paid in full, in pesos, for this booking confirms it. Safe to call any number
     * of times — from the webhook, the return page, and the expiry sweep.
     */
    public function handle(Payment $payment): Payment
    {
        if (! $payment->isPending()) {
            return $payment;
        }

        $result = $this->gateways->for($payment)->retrieveCheckout($payment);

        if ($result->failed) {
            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'failure_reason' => __('The payment was declined. You can try again with another method.'),
                'payload' => $result->raw,
            ])->save();

            return $payment;
        }

        if (! $result->paid) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $result): Payment {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $payment->isPending()) {
                return $payment;
            }

            if ($problem = $this->mismatch($payment, $result)) {
                $payment->forceFill([
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => $problem,
                    'payload' => $result->raw,
                ])->save();

                return $payment;
            }

            $booking = Booking::whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();
            $confirmable = $booking->status === BookingStatus::AwaitingPayment;

            $payment->forceFill([
                'status' => $confirmable ? PaymentStatus::Paid : PaymentStatus::RefundDue,
                'paid_at' => now(),
                'provider_payment_id' => $result->paymentId,
                'failure_reason' => $confirmable ? null : __('Paid after the booking was :status; the renter must be refunded.', ['status' => mb_strtolower($booking->status->label())]),
                'payload' => $result->raw,
            ])->save();

            if ($confirmable) {
                $this->transitions->handle($booking, BookingStatus::Confirmed, note: __('Payment :reference of ₱:amount received via :method.', [
                    'reference' => $payment->reference,
                    'amount' => number_format($payment->amountInPesos(), 2),
                    'method' => $payment->method->label(),
                ]));
            }

            return $payment;
        });
    }

    /**
     * Why a paid checkout does not match what this booking owes, if it does not.
     */
    protected function mismatch(Payment $payment, CheckoutResult $result): ?string
    {
        $booking = $payment->booking;

        // Audit-trail wording, kept untranslated so it reads the same to support staff.
        return match (true) {
            $result->amount !== $payment->amount || $payment->amount !== $booking->totalInCentavos() => sprintf(
                'Amount mismatch: expected %d centavos, the gateway reported %s.',
                $booking->totalInCentavos(),
                $result->amount ?? 'none',
            ),
            mb_strtoupper($result->currency ?? '') !== 'PHP' => sprintf('Currency mismatch: the gateway reported %s.', $result->currency ?? 'none'),
            $result->referenceNumber !== null && $result->referenceNumber !== $booking->reference => sprintf('Reference mismatch: the gateway reported %s.', $result->referenceNumber),
            default => null,
        };
    }
}
