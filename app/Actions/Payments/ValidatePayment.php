<?php

namespace App\Actions\Payments;

use App\Actions\Bookings\TransitionBooking;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\CheckoutResult;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class ValidatePayment
{
    public function __construct(
        protected ResolvePaymentGateway $gateways,
        protected TransitionBooking $transitions,
        protected AbandonPayment $abandonPayment,
    ) {}

    /**
     * Ask the gateway what happened to a payment and act on it: a payment that is
     * paid in full, in pesos, for this booking confirms it. Safe to call any number
     * of times — from the webhook, the return page, and the expiry sweep.
     *
     * Expired payments are checked too: a renter can still pay a checkout CarHub
     * gave up on, and that money must be recorded (as refund due if the booking
     * has moved on) rather than lost.
     */
    public function handle(Payment $payment): Payment
    {
        if (! $payment->canStillSettle()) {
            return $payment;
        }

        $result = $this->gateways->for($payment)->retrieveCheckout($payment);

        if ($result->failed && $payment->isPending()) {
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

        $payment = DB::transaction(function () use ($payment, $result): Payment {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $payment->canStillSettle()) {
                return $payment;
            }

            if ($problem = $this->mismatch($payment, $result)) {
                $payment->forceFill([
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => $problem,
                    'payload' => $result->raw,
                ])->save();

                ActivityLogger::record('payment.rejected', __('Payment :reference rejected: :reason', ['reference' => $payment->reference, 'reason' => $problem]), $payment);

                return $payment;
            }

            $booking = Booking::whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();
            $confirmable = $booking->status === BookingStatus::AwaitingPayment;

            $payment->forceFill([
                'status' => $confirmable ? PaymentStatus::Paid : PaymentStatus::RefundDue,
                'method' => $result->method ?? $payment->method,
                'paid_at' => now(),
                'provider_payment_id' => $result->paymentId,
                'failure_reason' => $confirmable ? null : __('Paid after the booking was :status; the renter must be refunded.', ['status' => mb_strtolower($booking->status->label())]),
                'payload' => $result->raw,
            ])->save();

            ActivityLogger::record(
                $confirmable ? 'payment.paid' : 'payment.refund_due',
                $confirmable
                    ? __('Payment :reference of ₱:amount received.', ['reference' => $payment->reference, 'amount' => number_format($payment->amountInPesos(), 2)])
                    : __('Payment :reference arrived after the booking closed; refund due.', ['reference' => $payment->reference]),
                $payment,
            );

            if ($confirmable) {
                $this->transitions->handle($booking, BookingStatus::Confirmed, note: __('Payment :reference of ₱:amount received via :method.', [
                    'reference' => $payment->reference,
                    'amount' => number_format($payment->amountInPesos(), 2),
                    'method' => $payment->methodLabel(),
                ]));
            }

            return $payment;
        });

        if ($payment->status === PaymentStatus::Paid) {
            $this->closeOtherCheckouts($payment);
        }

        return $payment;
    }

    /**
     * Once a booking is paid, close its other open checkouts so the renter cannot pay twice.
     */
    protected function closeOtherCheckouts(Payment $payment): void
    {
        Payment::whereBelongsTo($payment->booking)
            ->whereKeyNot($payment->getKey())
            ->where('status', PaymentStatus::Pending)
            ->get()
            ->each(fn (Payment $other) => $this->abandonPayment->handle($other));
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
