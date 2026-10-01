<?php

namespace App\Actions\Bookings;

use App\Actions\Payments\ValidatePayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpireStaleBookings
{
    public function __construct(
        protected TransitionBooking $transitions,
        protected ValidatePayment $validatePayment,
    ) {}

    /**
     * Release vehicles held by bookings that will never go ahead: unpaid past
     * their payment deadline, or still waiting on the owner or the renter's
     * checkout when pickup time arrives. Returns how many bookings expired.
     */
    public function handle(): int
    {
        $expired = 0;

        $unpaid = Booking::where('status', BookingStatus::AwaitingPayment)->where('payment_due_at', '<', now())->get();

        foreach ($unpaid as $booking) {
            // Catch payments whose webhook never arrived before giving up on them.
            if ($this->settledLate($booking)) {
                continue;
            }

            $this->expire($booking, __('Payment was not completed before the deadline.'));
            $expired++;
        }

        $neverReady = Booking::whereIn('status', [BookingStatus::Requested, BookingStatus::Approved])->where('pickup_at', '<', now())->get();

        foreach ($neverReady as $booking) {
            $this->expire($booking, $booking->status === BookingStatus::Requested
                ? __('The owner did not answer before pickup time.')
                : __('Checkout was not completed before pickup time.'));
            $expired++;
        }

        return $expired;
    }

    /**
     * Whether a pending payment turns out to have gone through after all.
     */
    protected function settledLate(Booking $booking): bool
    {
        foreach ($booking->payments()->where('status', PaymentStatus::Pending)->get() as $payment) {
            try {
                if ($this->validatePayment->handle($payment)->status === PaymentStatus::Paid) {
                    return true;
                }
            } catch (Throwable $exception) {
                Log::warning('Could not re-check a pending payment before expiring its booking.', ['payment' => $payment->reference, 'exception' => $exception->getMessage()]);
            }
        }

        return false;
    }

    /**
     * Expire the booking and abandon its unfinished payments.
     */
    protected function expire(Booking $booking, string $reason): void
    {
        $this->transitions->handle($booking, BookingStatus::Expired, note: $reason);

        Payment::whereBelongsTo($booking)->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Expired]);
    }
}
