---
paths:
  - 'routes/**'
---

# Routes

## Administrators operate the platform, they do not use it
Admin accounts never rent, list, or verify themselves. Renting and hosting routes (renter dashboard, trips, identity, owner/*) sit behind the `marketplace` middleware (EnsureMarketplaceAccount), which redirects admins to admin.dashboard. The list-vehicles gate excludes admins, CreateBookingRequest, SubmitOwnerApplication, and SubmitIdentityDocument refuse admins, and AppNavigation::sections() gives admins only the Administration and Account areas. Admins can still open documents.show and bookings.contract, which they need for reviews. Put new renter or owner routes inside the marketplace group.
