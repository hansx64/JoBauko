<?php

namespace Tests\Feature\Notifications;

use App\Services\Sms\SemaphoreSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemaphoreGatewayTest extends TestCase
{
    public function test_a_message_is_sent_and_its_id_recorded()
    {
        Http::fake(['api.semaphore.co/*' => Http::response([['message_id' => 12345, 'status' => 'Pending']])]);

        $result = (new SemaphoreSmsGateway('secret-key', 'JOBAUKO'))->send('09171234567', 'Hello');

        $this->assertTrue($result->successful);
        $this->assertSame('12345', $result->providerMessageId);
        Http::assertSent(fn (Request $request) => $request['apikey'] === 'secret-key'
            && $request['number'] === '09171234567'
            && $request['message'] === 'Hello'
            && $request['sendername'] === 'JOBAUKO');
    }

    public function test_errors_are_reported_as_failures()
    {
        Http::fake(['api.semaphore.co/*' => Http::response(['number' => ['The number format is invalid.']])]);

        $result = (new SemaphoreSmsGateway('secret-key'))->send('123', 'Hello');

        $this->assertFalse($result->successful);
        $this->assertStringContainsString('number format is invalid', $result->error);
    }

    public function test_the_driver_is_selected_by_configuration()
    {
        config(['sms.driver' => 'semaphore', 'sms.semaphore.api_key' => 'secret-key']);
        $this->app->forgetInstance(SmsGateway::class);

        $this->assertInstanceOf(SemaphoreSmsGateway::class, $this->app->make(SmsGateway::class));
    }
}
