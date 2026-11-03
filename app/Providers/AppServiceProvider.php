<?php

namespace App\Providers;

use App\Services\Mail\GmailTransport;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Services\Storage\GoogleDriveDisk;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
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

        // Google Drive as a storage disk for private uploads (UPLOADS_DISK=google).
        Storage::extend('google', fn ($app, array $config) => GoogleDriveDisk::make($config));
        Mail::extend('gmail', fn (array $config) => GmailTransport::make($config));

        // An overall ceiling against scraping and runaway clients; individual
        // actions are throttled more tightly in the route files.
        RateLimiter::for('web', fn (Request $request) => Limit::perMinute(config('jobauko.requests_per_minute'))
            ->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));

        // Stricter passwords in production, including a check against known
        // breached passwords (k-anonymity lookup; the password never leaves the server).
        Password::defaults(fn () => $this->app->environment('production')
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));
    }
}
