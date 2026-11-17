<?php

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Services\Payments\PayMongoGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * PayMongo does not say which methods an account has activated, and a checkout
 * naming a method the account lacks is still created; the renter just finds no way
 * to pay. This opens a throwaway checkout so it can be seen before a renter does.
 * Nothing is charged: an unpaid checkout is only an offer.
 */
#[Signature('paymongo:check
    {--methods= : Comma-separated PayMongo methods to ask for, e.g. qrph,gcash. Defaults to PAYMONGO_METHODS}
    {--amount=100 : What to pretend to charge, in whole pesos}')]
#[Description('Check the PayMongo setup and open a throwaway checkout showing which payment methods the account offers.')]
class PayMongoCheckCommand extends Command
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

        $methods = filled($this->option('methods'))
            ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('methods')))))
            : array_map(fn (PaymentMethod $method): string => $method->payMongoType(), PaymentMethod::enabled());

        $this->components->twoColumnDetail('Driver', (string) config('carhub.payments.driver'));
        $this->components->twoColumnDetail('Key', $gateway->keyMode());
        $this->components->twoColumnDetail('Webhook secret', filled(config('services.paymongo.webhook_secret')) ? 'set' : '<fg=red>missing</>');
        $this->components->twoColumnDetail('Webhook URL', route('webhooks.paymongo'));
        $this->components->twoColumnDetail('Methods', $methods === [] ? '<fg=red>none</>' : implode(', ', $methods));

        if ($methods === []) {
            $this->error('No payment methods: set PAYMONGO_METHODS, e.g. "qrph".');

            return self::FAILURE;
        }

        try {
            $session = $gateway->openProbeCheckout($methods, max(20, (int) $this->option('amount')) * 100);
        } catch (Throwable $exception) {
            $detail = $exception instanceof RequestException
                ? (string) ($exception->response->json('errors.0.detail') ?? $exception->getMessage())
                : $exception->getMessage();

            $this->error('PayMongo refused the checkout: '.$detail);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('PayMongo accepted the checkout. Open it to see which methods can actually be paid with:');
        $this->line('  '.$session->url);

        return self::SUCCESS;
    }
}
