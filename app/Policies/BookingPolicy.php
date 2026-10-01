<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * The renter, the vehicle's owner, and administrators can see a booking.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $user->id === $booking->renter_id || $user->id === $booking->owner_id;
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

    /**
     * Live and recent tracking: the owner and administrators can follow an ongoing
     * or recently completed rental; the renter only while they are on the trip.
     */
    public function track(User $user, Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Ongoing) {
            return $this->view($user, $booking);
        }

        return $booking->status === BookingStatus::Completed
            && ($user->isAdmin() || $user->id === $booking->owner_id);
    }

    /**
     * The renter rates a completed rental, once.
     */
    public function review(User $user, Booking $booking): bool
    {
        return $user->id === $booking->renter_id
            && $booking->status === BookingStatus::Completed
            && ! $booking->review()->exists();
    }
}
