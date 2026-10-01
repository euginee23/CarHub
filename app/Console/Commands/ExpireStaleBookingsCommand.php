<?php

namespace App\Console\Commands;

use App\Actions\Bookings\ExpireStaleBookings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:expire-stale')]
#[Description('Expire bookings that were not paid in time or never got past checkout before pickup, releasing their vehicles.')]
class ExpireStaleBookingsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExpireStaleBookings $expireStaleBookings): int
    {
        $count = $expireStaleBookings->handle();

        $this->info(trans_choice('{0} No bookings expired.|{1} Expired :count booking.|[2,*] Expired :count bookings.', $count, ['count' => $count]));

        return self::SUCCESS;
    }
}
