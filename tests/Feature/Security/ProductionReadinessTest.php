<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_development_setup_fails_the_check()
    {
        $this->artisan('jobauko:check')
            ->expectsOutputToContain('Set APP_ENV=production.')
            ->assertFailed();
    }

    public function test_a_properly_configured_installation_passes()
    {
        $this->seed(LocationSeeder::class);
        User::factory()->admin()->create();
        $this->app['env'] = 'production';
        config([
            'app.debug' => false,
            'app.url' => 'https://jobauko.bauko.gov.ph',
            'session.secure' => true,
            'session.encrypt' => true,
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.from.address' => 'no-reply@bauko.gov.ph',
            'jobauko.privacy_contact_email' => 'dpo@bauko.gov.ph',
            'sms.driver' => 'semaphore',
            'logging.channels.single.level' => 'warning',
        ]);
        File::shouldReceive('exists')->andReturnUsing(fn (string $path) => ! str_ends_with($path, 'hot'));

        $this->artisan('jobauko:check')
            ->expectsOutputToContain('Ready for production.')
            ->assertSuccessful();
    }
}
