<?php

namespace App\Actions\Bookings;

use App\Actions\Payments\AbandonPayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelBooking
{
    public function __construct(
        protected TransitionBooking $transitions,
        protected AbandonPayment $abandonPayment,
    ) {}

    /**
     * Cancel a booking before any payment has been taken, closing any checkout
     * the renter left open so it can no longer be paid.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        if (! $booking->isCancellableByRenter()) {
            throw ValidationException::withMessages(['booking' => __('This booking can no longer be cancelled here.')]);
        }

        $booking = $this->transitions->handle($booking, BookingStatus::Cancelled, $actor, $reason, [
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
            'cancellation_reason' => $reason,
        ]);

        $booking->payments()->where('status', PaymentStatus::Pending)->get()
            ->each(fn (Payment $payment) => $this->abandonPayment->handle($payment));

        return $booking;
    }
}
