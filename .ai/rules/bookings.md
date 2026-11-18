---
paths:
  - 'app/Actions/Bookings/**'
---

# Bookings

## Change booking status only through TransitionBooking
Every booking status change goes through App\Actions\Bookings\TransitionBooking::handle(). It enforces BookingStatus::canTransitionTo(), writes the BookingStatusChange audit row, and notifies the other party. Never set status directly: it is not mass-assignable, so update(['status' => ...]) silently does nothing (in tests, use forceFill or the factory's status() state). Only BookingStatus::holding() statuses (Approved, AwaitingPayment, Confirmed, Ongoing) block a vehicle; Requested does not.

## Late pickup: approved checkouts stay open until the return time
Product decision (Nov 2026): once a request is approved, the renter may finish checkout after the pickup time and pick the car up late. ExpireStaleBookings expires Approved bookings only when return_at passes. SignRentalContract refuses signing only once return_at has passed, and caps payment_due_at at return_at, not pickup_at. Requested bookings still expire at pickup_at, because the rental company must answer before pickup (ApproveBooking refuses after it, and the company dashboard counts down to it). Don't reintroduce pickup-time deadlines for Approved or AwaitingPayment.
