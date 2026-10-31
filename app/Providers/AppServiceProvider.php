<?php

namespace App\Providers;

use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\ServiceProvider;
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
        //
    }
}
