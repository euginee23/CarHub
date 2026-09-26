---
paths:
  - config/demo.php
---

# Config

## Vehicle listings live in the database, not config/demo.php
config/demo.php now only holds marketing copy (testimonials, faqs). Vehicles come from the vehicles table (Vehicle::listed()). Sample data is Database\Seeders\VehicleSeeder, and tests that need the catalogue seed it with $this->seed(VehicleSeeder::class). Only status=listed vehicles may appear publicly; the show route 404s for anything else.
