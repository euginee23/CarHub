---
paths:
  - 'app/Actions/Payments/**'
---

# Payments

## Payments are validated against the gateway, never the request
Payment outcomes are only decided by ValidatePayment, which re-fetches the checkout from the gateway (PaymentGateway::retrieveCheckout) and checks the amount in centavos, PHP currency, and booking reference before confirming. Webhook bodies and return redirects are just triggers. ValidatePayment is idempotent and is called from the PayMongo webhook, the return page, and the bookings:expire-stale sweep. The driver is config carhub.payments.driver (PAYMENT_DRIVER): "paymongo" or "simulated", and simulated is refused in production. Only report CheckoutResult::failed when the checkout can never be paid; a declined card inside an open PayMongo checkout can still be retried. Money that arrives after a booking expired becomes PaymentStatus::RefundDue, not a confirmation.
