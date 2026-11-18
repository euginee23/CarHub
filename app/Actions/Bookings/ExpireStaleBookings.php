<?php

namespace App\Actions\Bookings;

use App\Actions\Payments\AbandonPayment;
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
        protected AbandonPayment $abandonPayment,
    ) {}

    /**
     * Release vehicles held by bookings that will never go ahead: unpaid past
     * their payment deadline, still waiting on the rental company at pickup time,
     * or still in the renter's checkout when the rental period ends (renters may
     * finish checkout late and pick the vehicle up late). Returns how many expired.
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

        $neverAnswered = Booking::where('status', BookingStatus::Requested)->where('pickup_at', '<', now())->get();

        foreach ($neverAnswered as $booking) {
            $this->expire($booking, __('The rental company did not answer before pickup time.'));
            $expired++;
        }

        $neverCheckedOut = Booking::where('status', BookingStatus::Approved)->where('return_at', '<', now())->get();

        foreach ($neverCheckedOut as $booking) {
            $this->expire($booking, __('Checkout was not completed before the rental period ended.'));
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
     * Expire the booking and close its unfinished checkouts at the gateway.
     */
    protected function expire(Booking $booking, string $reason): void
    {
        $this->transitions->handle($booking, BookingStatus::Expired, note: $reason);

        Payment::whereBelongsTo($booking)->where('status', PaymentStatus::Pending)->get()
            ->each(fn (Payment $payment) => $this->abandonPayment->handle($payment));
    }
}
