---
paths:
  - 'app/Actions/Bookings/**'
---

# Bookings

## Change booking status only through TransitionBooking
Every booking status change goes through App\Actions\Bookings\TransitionBooking::handle(). It enforces BookingStatus::canTransitionTo(), writes the BookingStatusChange audit row, and notifies the other party. Never set status directly: it is not mass-assignable, so update(['status' => ...]) silently does nothing (in tests, use forceFill or the factory's status() state). Only BookingStatus::holding() statuses (Approved, AwaitingPayment, Confirmed, Ongoing) block a vehicle; Requested does not.
