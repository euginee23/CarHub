<?php

namespace App\Jobs;

use App\Actions\Forecasting\ForecastDemand;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a demand forecast in the background, for the admin "Run forecast now" button.
 */
class RunDemandForecast implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Training can take a few minutes.
     */
    public int $timeout = 900;

    /**
     * Execute the job.
     */
    public function handle(ForecastDemand $forecastDemand): void
    {
        $forecastDemand->handle();
    }
}
