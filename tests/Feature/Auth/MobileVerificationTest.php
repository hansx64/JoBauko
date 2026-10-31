<?php

namespace Tests\Feature\Auth;

use App\Models\Barangay;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsResult;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{to: string, message: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsGateway::class, new class($this->sent) implements SmsGateway
        {
            public function __construct(private array &$sent) {}

            public function send(string $mobileNumber, string $message): SmsResult
            {
                $this->sent[] = ['to' => $mobileNumber, 'message' => $message];

                return SmsResult::sent('test-id');
            }

            public function name(): string
            {
                return 'fake';
            }
        });
    }

    private function sentCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sent)['message'], $matches);

        return $matches[1];
    }

    public function test_verification_page_can_be_rendered()
    {
        $this->actingAs(User::factory()->seeker()->create())
            ->get('/verify-mobile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/verify-mobile')->where('codePending', false));
    }

    public function test_a_code_is_sent_and_logged_without_the_code_itself()
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->post('/verify-mobile/code')->assertSessionHas('status', 'verification-code-sent');

        $this->assertCount(1, $this->sent);
        $this->assertSame($user->mobile_number, $this->sent[0]['to']);

        $log = SmsLog::sole();
        $this->assertSame('verification', $log->topic);
        $this->assertSame('sent', $log->status);
        $this->assertStringNotContainsString($this->sentCode(), $log->message);
    }

    public function test_the_correct_code_verifies_the_mobile_number()
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->post('/verify-mobile/code');
        $this->post('/verify-mobile', ['code' => $this->sentCode()])->assertRedirect('/dashboard');

        $this->assertTrue($user->fresh()->hasVerifiedMobile());
    }

    public function test_a_wrong_code_is_rejected()
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->post('/verify-mobile/code');
        $wrong = $this->sentCode() === '000000' ? '111111' : '000000';

        $this->post('/verify-mobile', ['code' => $wrong])->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedMobile());
    }

    public function test_the_code_is_discarded_after_too_many_wrong_attempts()
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->post('/verify-mobile/code');
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, config('jobauko.mobile_verification.max_attempts')) as $attempt) {
            $this->post('/verify-mobile', ['code' => $wrong]);
        }

        $this->post('/verify-mobile', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedMobile());
    }

    public function test_changing_the_mobile_number_resets_verification()
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->seeker()->create(['mobile_verified_at' => now()]);

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'mobile_number' => '09998887777',
            'barangay_id' => Barangay::value('id'),
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_a_code_for_a_previous_number_does_not_verify_the_new_one()
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->post('/verify-mobile/code');
        $code = $this->sentCode();

        $user->forceFill(['mobile_number' => '09998887777'])->save();

        $this->post('/verify-mobile', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedMobile());
    }
}
