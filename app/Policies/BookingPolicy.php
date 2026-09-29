<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * The renter, the vehicle's owner, and administrators can see a booking.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->is_admin || $user->id === $booking->renter_id || $user->id === $booking->owner_id;
    }

    /**
     * Only the vehicle's owner answers booking requests and manages the handover.
     */
    public function manage(User $user, Booking $booking): bool
    {
        return $user->id === $booking->owner_id;
    }

    /**
     * Only the renter works through checkout: terms, IDs, contract, and payment.
     */
    public function checkout(User $user, Booking $booking): bool
    {
        return $user->id === $booking->renter_id;
    }

    /**
     * The renter can cancel until payment has been taken.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->id === $booking->renter_id && $booking->isCancellableByRenter();
    }
}
