<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Validation\ValidationException;

class MarkPaymentRefunded
{
    /**
     * Record that money owed back to a renter has been returned. The refund
     * itself is issued in the payment provider's dashboard; this keeps CarHub's
     * records in step with it.
     *
     * @throws ValidationException
     */
    public function handle(Payment $payment, User $administrator, ?string $note = null): Payment
    {
        if ($payment->status !== PaymentStatus::RefundDue) {
            throw ValidationException::withMessages(['refund' => __('Only payments marked "refund due" can be marked refunded.')]);
        }

        $payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'failure_reason' => trim(($payment->failure_reason ?? '').' '.__('Refunded :date.', ['date' => now()->format('M j, Y')]).($note ? ' '.$note : '')),
        ])->save();

        ActivityLogger::record('payment.refunded', __('Payment :reference of ₱:amount refunded.', [
            'reference' => $payment->reference,
            'amount' => number_format($payment->amountInPesos(), 2),
        ]), $payment, array_filter(['note' => $note]), $administrator);

        return $payment;
    }
}
