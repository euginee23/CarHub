---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Renters are blocked until two IDs are approved
EnsureRenterIsVerified (alias identity.verified) sends renters without User::hasVerifiedIdentity() (two approved IDs of different types) to identity.edit; registration lands renters there too. Admins see each renter's IdentityStatus (NotUploaded, Pending, Approved) on admin.users; compute it with IdentityStatus::fromCounts() or User::withIdentityStatus() rather than re-deriving the thresholds.
