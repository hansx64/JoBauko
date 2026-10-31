<?php

namespace App\Providers;

use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, fn () => match (config('sms.driver')) {
            'log' => new LogSmsGateway,
            'semaphore' => new SemaphoreSmsGateway(
                (string) config('sms.semaphore.api_key') ?: throw new InvalidArgumentException('SEMAPHORE_API_KEY is not set.'),
                config('sms.semaphore.sender_name') ?: null,
            ),
            default => throw new InvalidArgumentException('Unsupported SMS driver ['.config('sms.driver').'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($proxies = config('jobauko.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Generated links (emails, signed invitations) must use HTTPS when the site does.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Stricter passwords in production, including a check against known
        // breached passwords (k-anonymity lookup; the password never leaves the server).
        Password::defaults(fn () => $this->app->environment('production')
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));
    }
}
