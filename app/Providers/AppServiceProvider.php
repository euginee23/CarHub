<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\User;
use App\Services\Payments\PayMongoGateway;
use App\Services\Payments\SimulatedGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            $driver = config('carhub.payments.driver');

            if ($driver === 'simulated' && app()->isProduction()) {
                throw new RuntimeException('The simulated payment gateway cannot be used in production.');
            }

            return match ($driver) {
                'paymongo' => new PayMongoGateway(config('services.paymongo.secret_key'), config('services.paymongo.base_url')),
                'simulated' => new SimulatedGateway,
                default => throw new RuntimeException("Unknown payment driver [{$driver}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Configure the role-based authorization gates.
     */
    protected function configureGates(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());

        Gate::define('list-vehicles', fn (User $user): bool => $user->isVerifiedOwner());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
