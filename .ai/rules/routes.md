---
paths:
  - 'routes/**'
---

# Routes

## Renters, owners, and admins are separate kinds of account
Every user has exactly one `role` (App\Enums\UserRole: Renter, Owner, Admin), chosen at registration (admins are created directly). There is no is_admin column; use isAdmin(), isOwner(), isRenter(), and isVerifiedOwner() (an Owner with owner_verified_at). Renting routes (renter dashboard, trips, identity) are behind `role:renter`; hosting routes (owner/apply and owner/*) are behind `role:owner`, plus `can:list-vehicles` for everything past verification; admin routes use `can:access-admin`. EnsureUserHasRole sends anyone in the wrong area to /dashboard, which routes by role (unverified owners land on owner.apply). The actions enforce roles too: CreateBookingRequest is renters only, SubmitOwnerApplication owners only, SubmitIdentityDocument renters only. Admins can still open documents.show and bookings.contract for reviews. In tests use the UserFactory states renter(), owner(), verifiedOwner(), and admin().

## Fixing a wrong account type
Registration requires account_type (renter or owner); "List your vehicle" links use route('register', ['as' => 'owner']) to preselect owner. Admins correct mistakes on admin.users through App\Actions\Users\ChangeAccountType. It switches renter and owner only, never admin, and refuses when the account already has bookings (renter) or vehicles (owner). Switching always clears owner_verified_at.
