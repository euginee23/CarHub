---
paths:
  - 'routes/**'
---

# Routes

## Renters, rental companies, and admins are separate kinds of account
CarHub lists vehicles for rental companies, not private owners; there is no "owner" anywhere (the old owner_* names were migrated away). Every user has exactly one `role` (App\Enums\UserRole: Renter, Company, Admin), chosen at registration (admins are created directly). There is no is_admin column; use isAdmin(), isCompany(), isRenter(), and isVerifiedCompany() (a Company with company_verified_at). Vehicles, bookings, and reviews point at the company through company_id / company(); show a company with User::displayName() (its approved company_name, else the account name). Renting routes (renter dashboard, trips, checkout, payments) are behind `role:renter` plus `identity.verified`; the identity page (identity.edit) is `role:renter` only. Company routes (company/apply and company/*) are behind `role:company`, plus `can:list-vehicles` for everything past verification; admin routes use `can:access-admin`. EnsureUserHasRole sends anyone in the wrong area to /dashboard, which routes by role (unverified companies land on company.apply, renters without two approved IDs on identity.edit). The actions enforce roles too: CreateBookingRequest is verified renters only, SubmitCompanyApplication companies only, SubmitIdentityDocument renters only. Admins can still open documents.show and bookings.contract for reviews. In tests use the UserFactory states renter() (no IDs), withVerifiedIdentity(), company(), verifiedCompany(), and admin(); BookingFactory's renter already has verified IDs.

## Fixing a wrong account type
Registration requires account_type (renter or company); "List your vehicle" links use route('register', ['as' => 'company']) to preselect company. Admins correct mistakes on admin.users through App\Actions\Users\ChangeAccountType. It switches renter and company only, never admin, and refuses when the account already has bookings (renter) or vehicles (company). Switching always clears company_verified_at.
