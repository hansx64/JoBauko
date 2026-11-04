<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\Classification;
use App\Enums\ConsentType;
use App\Enums\NotificationTopic;
use App\Models\Barangay;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LocationSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'mobile_number' => '0917 123 4567',
            'barangay_id' => Barangay::where('name', 'Poblacion')->value('id'),
            'classification' => Classification::Student->value,
            'password' => 'Highland-Harvest-2026',
            'password_confirmation' => 'Highland-Harvest-2026',
            'privacy_consent' => true,
            'sms_consent' => false,
            ...$overrides,
        ];
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('auth/register')
            ->has('barangays', 22)
            ->has('classifications', count(Classification::cases())));
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', $this->validData());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'maria@example.com')->firstOrFail();

        $this->assertSame(AccountType::Seeker, $user->account_type);
        $this->assertSame('09171234567', $user->mobile_number);
        $this->assertNull($user->mobile_verified_at);
        $this->assertSame('Poblacion', $user->barangay->name);
        $this->assertSame(Classification::Student, $user->applicantProfile->classification);
    }

    public function test_registration_records_privacy_consent_with_version()
    {
        $this->post('/register', $this->validData());

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $consent = $user->consents()->where('type', ConsentType::PrivacyNotice)->firstOrFail();

        $this->assertSame(config('jobauko.privacy_notice_version'), $consent->version);
        $this->assertNotNull($consent->ip_address);
        $this->assertFalse($user->hasActiveConsent(ConsentType::Sms));
    }

    public function test_sms_consent_is_recorded_only_when_given()
    {
        $this->post('/register', $this->validData(['sms_consent' => true]));

        $user = User::where('email', 'maria@example.com')->firstOrFail();

        $this->assertTrue($user->hasActiveConsent(ConsentType::Sms));
    }

    public function test_privacy_consent_is_required()
    {
        $this->post('/register', $this->validData(['privacy_consent' => false]))
            ->assertSessionHasErrors('privacy_consent');

        $this->assertGuest();
    }

    public function test_account_type_cannot_be_chosen_by_the_registrant()
    {
        $this->post('/register', $this->validData(['account_type' => 'admin']));

        $this->assertSame(AccountType::Seeker, User::where('email', 'maria@example.com')->value('account_type'));
    }

    public function test_barangay_must_exist()
    {
        $this->post('/register', $this->validData(['barangay_id' => 999999]))
            ->assertSessionHasErrors('barangay_id');
    }

    public function test_mobile_number_must_be_a_philippine_mobile_number()
    {
        $this->post('/register', $this->validData(['mobile_number' => '12345']))
            ->assertSessionHasErrors('mobile_number');
    }

    public function test_a_number_on_an_organization_account_can_still_register_as_a_job_seeker()
    {
        User::factory()->organization()->create(['mobile_number' => '09171234567']);

        $this->post('/register', $this->validData())->assertSessionHasNoErrors();

        $this->assertSame(AccountType::Seeker, User::where('email', 'maria@example.com')->sole()->account_type);
    }

    public function test_mobile_number_formats_are_normalized_before_the_uniqueness_check()
    {
        User::factory()->create(['mobile_number' => '09171234567']);

        $this->post('/register', $this->validData(['mobile_number' => '+63 917 123 4567']))
            ->assertSessionHasErrors('mobile_number');
    }

    /**
     * @return array<string, array{Classification, list<NotificationTopic>}>
     */
    public static function classificationDefaults(): array
    {
        return [
            'student' => [Classification::Student, [NotificationTopic::Scholarships]],
            'fresh graduate' => [Classification::FreshGraduate, [NotificationTopic::Jobs]],
            'employed' => [Classification::Employed, [NotificationTopic::Jobs, NotificationTopic::Livelihood]],
            'unemployed' => [Classification::Unemployed, [NotificationTopic::Jobs, NotificationTopic::Livelihood]],
            'self-employed' => [Classification::SelfEmployed, [NotificationTopic::Livelihood]],
            'career shifter' => [Classification::CareerShifter, [NotificationTopic::Jobs, NotificationTopic::Training]],
            'out-of-school youth' => [Classification::OutOfSchoolYouth, [NotificationTopic::Livelihood, NotificationTopic::Scholarships]],
            'other' => [Classification::Other, []],
        ];
    }

    #[DataProvider('classificationDefaults')]
    public function test_notification_preferences_default_from_classification(Classification $classification, array $expectedTopics)
    {
        $this->post('/register', $this->validData(['classification' => $classification->value]));

        $preferences = User::where('email', 'maria@example.com')->firstOrFail()
            ->notificationPreferences
            ->keyBy(fn ($preference) => $preference->topic->value);

        $this->assertCount(count(NotificationTopic::cases()), $preferences);

        $expected = [...$expectedTopics, ...NotificationTopic::alwaysSelected()];

        foreach (NotificationTopic::cases() as $topic) {
            $this->assertSame(
                in_array($topic, $expected, true),
                $preferences[$topic->value]->sms,
                "Unexpected SMS default for {$topic->value}",
            );
        }
    }
}
