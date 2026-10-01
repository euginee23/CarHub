<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Run `php artisan schedule:work` locally (or a cron calling schedule:run in
| production) so these fire.
|
*/

Schedule::command('bookings:expire-stale')->everyFifteenMinutes()->withoutOverlapping();

// Trip location history is kept for 30 days (VehicleLocation::RETENTION_DAYS).
Schedule::command('model:prune')->daily();
