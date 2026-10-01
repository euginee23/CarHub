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

// Prunes trip location history after 30 days and the activity log after a year.
Schedule::command('model:prune')->daily();
