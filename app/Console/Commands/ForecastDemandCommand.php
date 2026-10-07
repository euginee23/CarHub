<?php

namespace App\Console\Commands;

use App\Actions\Forecasting\ForecastDemand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demand:forecast')]
#[Description('Forecast booking demand per body type with the LSTM model (seasonal fallback where it cannot run).')]
class ForecastDemandCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ForecastDemand $forecastDemand): int
    {
        $this->info('Training and forecasting… this can take a minute.');

        $result = $forecastDemand->handle();

        $this->table(
            ['Body type', 'Method', 'Note'],
            collect($result['types'])->map(fn (array $type, string $name) => [$name, $type['method'], $type['note'] ?? ''])->values()->all(),
        );

        if ($result['error']) {
            $this->warn($result['error']);
        }

        $this->info("Forecast run {$result['run_id']} saved.");

        return self::SUCCESS;
    }
}
