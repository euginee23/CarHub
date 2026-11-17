<?php

namespace App\Console\Commands;

use App\Services\Payments\PayMongoGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

#[Signature('paymongo:webhook
    {--create : Register CarHub\'s webhook endpoint on the PayMongo account}
    {--url= : The endpoint to register. Defaults to the webhooks.paymongo route on APP_URL}')]
#[Description('List the PayMongo account\'s webhooks, or register CarHub\'s and print its signing secret.')]
class PayMongoWebhookCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PayMongoGateway $gateway): int
    {
        if (blank(config('services.paymongo.secret_key'))) {
            $this->error('PAYMONGO_SECRET_KEY is not set.');

            return self::FAILURE;
        }

        $url = (string) ($this->option('url') ?: route('webhooks.paymongo'));

        try {
            return $this->option('create') ? $this->create($gateway, $url) : $this->list($gateway, $url);
        } catch (Throwable $exception) {
            $detail = $exception instanceof RequestException
                ? (string) ($exception->response->json('errors.0.detail') ?? $exception->getMessage())
                : $exception->getMessage();

            $this->error('PayMongo refused the request: '.$detail);

            return self::FAILURE;
        }
    }

    /**
     * Show the webhooks already registered on the account.
     */
    protected function list(PayMongoGateway $gateway, string $url): int
    {
        $webhooks = $gateway->webhooks();

        if ($webhooks === []) {
            $this->warn('No webhooks are registered. Run with --create to register '.$url);

            return self::SUCCESS;
        }

        $this->table(['ID', 'URL', 'Status', 'Events'], array_map(fn (array $webhook): array => [
            $webhook['id'] ?? '',
            data_get($webhook, 'attributes.url'),
            data_get($webhook, 'attributes.status'),
            implode(', ', (array) data_get($webhook, 'attributes.events', [])),
        ], $webhooks));

        return self::SUCCESS;
    }

    /**
     * Register the endpoint and print the signing secret PayMongo shows only once.
     */
    protected function create(PayMongoGateway $gateway, string $url): int
    {
        if (! str_starts_with($url, 'https://')) {
            $this->error('PayMongo only delivers webhooks over HTTPS; pass --url=https://… or set APP_URL.');

            return self::FAILURE;
        }

        $webhook = $gateway->createWebhook($url);

        $this->info('Registered '.$url.' for '.implode(', ', PayMongoGateway::WEBHOOK_EVENTS).'.');
        $this->newLine();
        $this->line('Put this in .env (PayMongo will not show it again):');
        $this->line('  PAYMONGO_WEBHOOK_SECRET='.data_get($webhook, 'attributes.secret_key'));

        return self::SUCCESS;
    }
}
