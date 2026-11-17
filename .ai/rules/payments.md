---
paths:
  - 'app/Actions/Payments/**'
---

# Payments

## Payments are validated against the gateway, never the request
Payment outcomes are only decided by ValidatePayment, which re-fetches the checkout from the gateway (PaymentGateway::retrieveCheckout) and checks the amount in centavos, PHP currency, and booking reference before confirming. Webhook bodies and return redirects are just triggers. ValidatePayment is idempotent and is called from the PayMongo webhook, the return page, and the bookings:expire-stale sweep. The driver is config carhub.payments.driver (PAYMENT_DRIVER): "paymongo" or "simulated", and simulated is refused in production. Only report CheckoutResult::failed when the checkout can never be paid; a declined card inside an open PayMongo checkout can still be retried. Money that arrives after a booking expired becomes PaymentStatus::RefundDue, not a confirmation.

## Methods are chosen on PayMongo; abandoned checkouts go through AbandonPayment
The renter picks how to pay on PayMongo's hosted page. CarHub offers PaymentMethod::enabled() (services.paymongo.methods from PAYMONGO_METHODS, PayMongo names, default qrph), so payments.method is null until paid and ValidatePayment fills it from the gateway's CheckoutResult::method. Display it with Payment::methodLabel(). Never just mark a pending payment Expired: use AbandonPayment, which also expires the checkout at PayMongo (StartPayment, CancelBooking, ExpireStaleBookings, the payments.cancel page, and sibling checkouts once a booking is confirmed). ValidatePayment re-checks Expired payments too (Payment::canStillSettle), so money paid into an abandoned checkout confirms a booking still awaiting payment, or becomes RefundDue, and is never lost. Set up a PayMongo account with `php artisan paymongo:check` and `paymongo:webhook --create`.
