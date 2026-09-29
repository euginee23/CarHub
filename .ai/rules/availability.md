---
paths:
  - 'app/Services/Availability/**'
---

# Availability

## One source of truth for vehicle availability
Availability is decided by Vehicle::scopeAvailableBetween(), which AvailabilityChecker::isAvailable(), browse's trip-date filter, and the booking panel all share. It currently excludes owner blackouts only. When bookings exist, add the overlap check for blocking booking statuses there instead of in callers. Scheduling limits (lead time, min/max duration, advance window) and the service fee live in config/carhub.php; price rentals with RentalQuote::for(), which charges per started 24 hours.
