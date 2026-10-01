<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\GpsDevice;
use App\Models\User;
use App\Services\Payments\PayMongoGateway;
use App\Services\Payments\SimulatedGateway;
use App\Support\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
        $this->recordAuthenticationActivity();
    }

    /**
     * Keep sign-ins, failed sign-ins, and sign-outs in the admin activity log.
     */
    protected function recordAuthenticationActivity(): void
    {
        Event::listen(Login::class, fn (Login $event) => $event->user instanceof User
            ? ActivityLogger::record('auth.login', __(':name signed in.', ['name' => $event->user->name]), $event->user, actor: $event->user)
            : null);

        Event::listen(Logout::class, fn (Logout $event) => $event->user instanceof User
            ? ActivityLogger::record('auth.logout', __(':name signed out.', ['name' => $event->user->name]), $event->user, actor: $event->user)
            : null);

        Event::listen(Failed::class, fn (Failed $event) => ActivityLogger::record(
            'auth.failed',
            __('Failed sign-in for :email.', ['email' => $event->credentials['email'] ?? __('an unknown email')]),
            $event->user instanceof User ? $event->user : null,
        ));
    }

    /**
     * Configure the rate limiters for device traffic.
     */
    protected function configureRateLimiting(): void
    {
        // A tracker reporting every few seconds stays well inside this.
        RateLimiter::for('gps', function (Request $request): Limit {
            $device = $request->attributes->get('gpsDevice');

            return Limit::perMinute(60)->by($device instanceof GpsDevice ? 'device:'.$device->id : 'ip:'.$request->ip());
        });
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
