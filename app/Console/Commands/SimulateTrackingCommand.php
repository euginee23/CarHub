<?php

namespace App\Console\Commands;

use App\Actions\Tracking\RecordVehicleLocations;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\GpsDevice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tracking:simulate
    {booking : The reference of an ongoing booking, e.g. BK-7Q2M9XKD}
    {--steps=40 : How many positions to send}
    {--interval=3 : Seconds to wait between positions}')]
#[Description('Drive a rented vehicle around on the live map without an ESP tracker. Local development only.')]
class SimulateTrackingCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecordVehicleLocations $recordVehicleLocations): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('The tracking simulator cannot run in production.');

            return self::FAILURE;
        }

        $booking = Booking::with(['vehicle.gpsDevice', 'locations'])->firstWhere('reference', $this->argument('booking'));

        if ($booking === null || $booking->status !== BookingStatus::Ongoing) {
            $this->error('Give the reference of a booking that is currently on a trip (the owner has released the vehicle).');

            return self::FAILURE;
        }

        $device = $booking->vehicle->gpsDevice ?? $this->pairSimulatedTracker($booking);

        $last = $booking->locations->last();
        $latitude = $last->latitude ?? $booking->vehicle->latitude ?? 10.3157;
        $longitude = $last->longitude ?? $booking->vehicle->longitude ?? 123.8854;
        $heading = random_int(0, 359);
        $steps = max(1, (int) $this->option('steps'));
        $interval = max(0, (int) $this->option('interval'));

        $this->info("Driving the {$booking->vehicle->name} for {$steps} positions. Open the booking's tracking page to watch.");

        $this->withProgressBar(range(1, $steps), function () use (&$latitude, &$longitude, &$heading, $device, $recordVehicleLocations, $interval): void {
            // Wander like a car on city streets: mostly straight, with gentle turns.
            $heading = ($heading + random_int(-35, 35) + 360) % 360;
            $speed = random_int(20, 60);
            $metres = $speed / 3.6 * 15;

            $latitude += ($metres * cos(deg2rad($heading))) / 111_320;
            $longitude += ($metres * sin(deg2rad($heading))) / (111_320 * cos(deg2rad($latitude)));

            $recordVehicleLocations->handle($device, [[
                'lat' => round($latitude, 7),
                'lng' => round($longitude, 7),
                'speed' => $speed,
                'heading' => $heading,
            ]]);

            if ($interval > 0) {
                sleep($interval);
            }
        });

        $this->newLine(2);
        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Pair a stand-in tracker with a vehicle that has none.
     */
    protected function pairSimulatedTracker(Booking $booking): GpsDevice
    {
        $device = new GpsDevice(['label' => 'Simulated tracker']);
        $device->vehicle()->associate($booking->vehicle);
        $device->token_hash = GpsDevice::hashToken(GpsDevice::newToken());
        $device->save();

        $this->comment('No tracker was connected, so a simulated one was paired with the vehicle.');

        return $device;
    }
}
