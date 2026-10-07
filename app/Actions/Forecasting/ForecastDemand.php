<?php

namespace App\Actions\Forecasting;

use App\Enums\VehicleType;
use App\Models\DemandForecast;
use App\Services\Forecasting\SeasonalForecaster;
use App\Services\Reports\RentalReports;
use App\Support\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Forecast booking demand per body type for the coming weeks: export request
 * history, train the LSTM in ml/forecast.py, and store its predictions. Any
 * body type the LSTM could not model gets the seasonal fallback instead, so
 * every type always has a forecast.
 */
class ForecastDemand
{
    public function __construct(protected SeasonalForecaster $seasonal) {}

    /**
     * Run a forecast and return what was produced per body type.
     *
     * @return array{run_id: string, types: array<string, array{method: string, note: string|null}>, error: string|null}
     */
    public function handle(): array
    {
        $config = config('carhub.forecasting');
        $today = CarbonImmutable::today();
        $lastDay = $today->subDay();
        $history = (new RentalReports($lastDay->subDays($config['history_days'] - 1), $lastDay))->dailyDemandByType();

        [$lstm, $error] = $this->runLstm($history, $config);

        $runId = (string) Str::uuid();
        $summary = [];
        $rows = [];

        foreach (VehicleType::cases() as $type) {
            $series = $lstm['series'][$type->value] ?? null;

            if ($series !== null) {
                $forecast = collect($series['forecast'])->mapWithKeys(fn (array $point) => [$point['date'] => (float) $point['value']])->all();
                $method = DemandForecast::METHOD_LSTM;
                $meta = ['model' => $lstm['version'] ?? 'lstm', 'val_mae' => $series['val_mae'] ?? null, 'train_windows' => $series['train_windows'] ?? null];
                $note = null;
            } else {
                $values = array_map(fn (array $types) => $types[$type->value] ?? 0, $history);
                $forecast = $this->seasonal->forecast($values, $lastDay, $config['horizon']);
                $method = DemandForecast::METHOD_SEASONAL;
                $note = $lstm['skipped'][$type->value] ?? $error ?? __('LSTM did not return this body type.');
                $meta = ['reason' => $note];
            }

            $summary[$type->value] = ['method' => $method, 'note' => $note];

            foreach ($forecast as $date => $value) {
                $rows[] = [
                    'vehicle_type' => $type->value,
                    'date' => $date,
                    'predicted_requests' => round(max(0, $value), 3),
                    'method' => $method,
                    'run_id' => $runId,
                    'meta' => json_encode($meta),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::transaction(function () use ($rows, $today): void {
            DemandForecast::where('date', '>=', $today->toDateString())->delete();
            DemandForecast::insert($rows);
        });

        $lstmTypes = collect($summary)->where('method', DemandForecast::METHOD_LSTM)->count();

        ActivityLogger::record('forecast.generated', __('Demand forecast generated: :lstm of :total body types by the LSTM model.', [
            'lstm' => $lstmTypes,
            'total' => count($summary),
        ]), properties: array_filter(['run_id' => $runId, 'error' => $error]));

        return ['run_id' => $runId, 'types' => $summary, 'error' => $error];
    }

    /**
     * Export history to CSV, run the Python LSTM, and read its JSON output.
     *
     * @param  array<string, array<string, int>>  $history
     * @param  array<string, mixed>  $config
     * @return array{0: array<string, mixed>, 1: string|null} The parsed output, and an error if it failed.
     */
    protected function runLstm(array $history, array $config): array
    {
        if (! is_executable($config['python']) || ! File::exists($config['script'])) {
            return [[], __('Python for the LSTM is not installed (see ml/requirements.txt).')];
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('ml');
        $input = $disk->path('ml/demand-history.csv');
        $output = $disk->path('ml/forecast.json');

        $types = array_map(fn (VehicleType $type) => $type->value, VehicleType::cases());
        $csv = implode(',', ['date', ...$types])."\n";

        foreach ($history as $day => $counts) {
            $csv .= implode(',', [$day, ...array_map(fn (string $type) => $counts[$type] ?? 0, $types)])."\n";
        }

        File::put($input, $csv);
        File::delete($output);

        try {
            $result = Process::timeout($config['timeout'])->run([
                $config['python'], $config['script'],
                '--input', $input,
                '--output', $output,
                '--horizon', (string) $config['horizon'],
                '--lookback', (string) $config['lookback'],
                '--min-days', (string) $config['min_history_days'],
            ]);
        } catch (Throwable $exception) {
            Log::error('The LSTM forecaster could not run.', ['exception' => $exception->getMessage()]);

            return [[], __('The LSTM forecaster could not run: :error', ['error' => $exception->getMessage()])];
        }

        if ($result->failed() || ! File::exists($output)) {
            Log::error('The LSTM forecaster failed.', ['exit' => $result->exitCode(), 'error' => $result->errorOutput()]);

            return [[], __('The LSTM forecaster failed (exit :code).', ['code' => $result->exitCode()])];
        }

        $decoded = json_decode(File::get($output), true);

        return is_array($decoded) ? [$decoded, null] : [[], __('The LSTM forecaster returned unreadable output.')];
    }
}
