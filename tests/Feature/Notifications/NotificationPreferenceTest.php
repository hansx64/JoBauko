<?php

namespace Tests\Feature\Notifications;

use App\Enums\ConsentType;
use App\Enums\NotificationTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{email: bool, sms: bool}>
     */
    private function choices(bool $email = false, bool $sms = false): array
    {
        return collect(NotificationTopic::cases())->mapWithKeys(fn (NotificationTopic $t) => [$t->value => ['email' => $email, 'sms' => $sms]])->all();
    }

    public function test_seekers_see_their_choices()
    {
        $seeker = User::factory()->seeker()->create();
        $seeker->notificationPreferences()->create(['topic' => NotificationTopic::Jobs, 'email' => true, 'sms' => false]);

        $this->actingAs($seeker)
            ->get('/settings/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/notifications')
                ->has('topics', 7)
                ->where('topics.0.value', 'jobs')
                ->where('topics.0.email', true)
                ->where('smsConsent', false)
                ->where('mobile.verified', false));
    }

    public function test_choices_and_sms_consent_are_saved()
    {
        $seeker = User::factory()->seeker()->create();
        $choices = $this->choices();
        $choices['status_updates'] = ['email' => true, 'sms' => true];

        $this->actingAs($seeker)
            ->put('/settings/notifications', ['preferences' => $choices, 'sms_consent' => true])
            ->assertSessionHasNoErrors();

        $preference = $seeker->notificationPreferences()->where('topic', 'status_updates')->sole();
        $this->assertTrue($preference->email);
        $this->assertTrue($preference->sms);
        $this->assertSame('user', $preference->source);
        $this->assertSame(7, $seeker->notificationPreferences()->count());
        $this->assertTrue($seeker->hasActiveConsent(ConsentType::Sms));

        // Withdrawing keeps the history of when consent was given.
        $this->put('/settings/notifications', ['preferences' => $choices, 'sms_consent' => false])->assertSessionHasNoErrors();
        $this->assertFalse($seeker->hasActiveConsent(ConsentType::Sms));
        $this->assertDatabaseHas('user_consents', ['user_id' => $seeker->id, 'type' => 'sms']);
    }

    public function test_input_is_validated()
    {
        $seeker = User::factory()->seeker()->create();

        $this->actingAs($seeker)
            ->put('/settings/notifications', ['preferences' => ['jobs' => ['email' => 'maybe', 'sms' => false]], 'sms_consent' => true])
            ->assertSessionHasErrors('preferences.jobs.email');
    }

    public function test_only_seekers_have_topic_preferences()
    {
        $this->actingAs(User::factory()->organization()->create())->get('/settings/notifications')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->put('/settings/notifications', ['preferences' => [], 'sms_consent' => false])->assertForbidden();
    }
}
