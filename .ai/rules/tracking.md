---
paths:
  - 'app/Actions/Tracking/**'
---

# Tracking

## GPS tracking is only recorded during an ongoing rental
ESP trackers POST to /api/v1/tracking/pings with "Authorization: Bearer <token>" (routes/api.php, AuthenticateGpsDevice, throttle:gps at 60/min per device). A token is issued by ConnectGpsDevice from the owner's vehicle page; only its SHA-256 hash is stored, and reissuing replaces the old one. RecordVehicleLocations stores fixes only while the vehicle has an Ongoing booking, as the rental terms promise; otherwise it returns 202 with stored 0. Fixes are clamped between the handover time and now. VehicleLocation is MassPrunable after 30 days (model:prune runs daily). The tracking page (bookings.tracking) uses BookingPolicy::track: the renter only during the trip, the owner and admins also after it. Rentals start and end only through StartRental and CompleteRental.

## One live map component
Show tracking with <livewire:tracking.live-map :booking="..."> (add :compact="true" on booking pages). Don't copy the map logic. It polls every 10s and dispatches `tracking-updated` tagged with the booking reference, so several maps can share a page. For local testing without an ESP, run `php artisan tracking:simulate <booking reference>`. It refuses in production, works only on Ongoing bookings, and feeds RecordVehicleLocations like a real device.

## GPS test page
/test-track-gps-map (route tracking.test, pages::admin.tracking-test) is for admins only, and only when config carhub.tracking.test_page is on (off in production; TRACKING_TEST_PAGE overrides). It pairs a tracker with any vehicle, shows the endpoint for the current host, sends positions from a phone or browser through the real API (resources/js/gps-sender.js), and lists recent requests from TrackerRequestLog (cache, 2 hours). The privacy rule still applies there, so use StartDemoTrip and EndDemoTrip (dev-only, no notifications, trip count unchanged) to put a vehicle on a rental for testing.
