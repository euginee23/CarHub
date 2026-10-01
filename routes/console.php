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
