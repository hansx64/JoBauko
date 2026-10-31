<?php

namespace Tests\Feature\Notifications;

use App\Enums\ApplicationStatus;
use App\Enums\ConsentType;
use App\Enums\NotificationTopic;
use App\Enums\OrganizationRole;
use App\Enums\SmsTemplateKey;
use App\Models\Application;
use App\Models\EmailLog;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Notifications\ApplicationStatusUpdated;
use App\Notifications\Channels\SmsChannel;
use App\Services\Privacy\ConsentRecorder;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SubscribesUsers;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase, SubscribesUsers;

    /**
     * @return array{staff: User, application: Application}
     */
    private function application(): array
    {
        $staff = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember(OrganizationRole::Admin, $staff)->create();
        $job = Opportunity::factory()->job()->published()->for($organization)->create(['title' => 'Farm Technician']);
        $seeker = User::factory()->seeker()->create(['name' => 'Maria Bayang']);

        return ['staff' => $staff, 'application' => Application::factory()->for($job)->for($seeker)->create()];
    }

    public function test_status_updates_go_out_by_every_channel_the_seeker_chose()
    {
        ['staff' => $staff, 'application' => $application] = $this->application();
        $this->subscribe($application->user, NotificationTopic::StatusUpdates);

        $this->actingAs($staff)
            ->post("/org/applications/{$application->id}/status", ['status' => 'shortlisted', 'remarks' => 'Interview on Monday.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $application->user->notifications()->count());

        $sms = SmsLog::sole();
        $this->assertSame('sent', $sms->status);
        $this->assertSame('status_updates', $sms->topic);
        $this->assertSame('JoBauko: Hi Maria, your application for Farm Technician is now Shortlisted. Interview on Monday.', $sms->message);

        $email = EmailLog::sole();
        $this->assertSame('Your application for Farm Technician: Shortlisted', $email->subject);
        $this->assertSame($application->user->email, $email->email);
    }

    public function test_sms_needs_consent_and_a_verified_number()
    {
        ['application' => $application] = $this->application();
        $seeker = $this->subscribe($application->user, NotificationTopic::StatusUpdates, email: false);

        app(ConsentRecorder::class)->withdraw($seeker, ConsentType::Sms);
        $this->assertNotContains(SmsChannel::class, (new ApplicationStatusUpdated($application))->via($seeker->refresh()));

        app(ConsentRecorder::class)->grant($seeker, ConsentType::Sms);
        $seeker->forceFill(['mobile_verified_at' => null])->save();
        $this->assertNotContains(SmsChannel::class, (new ApplicationStatusUpdated($application))->via($seeker->refresh()));

        $seeker->forceFill(['mobile_verified_at' => now()])->save();
        $this->assertSame(['database', SmsChannel::class], (new ApplicationStatusUpdated($application))->via($seeker->refresh()));
    }

    public function test_without_preferences_only_the_in_app_notification_is_sent()
    {
        ['application' => $application] = $this->application();

        $this->assertSame(['database'], (new ApplicationStatusUpdated($application))->via($application->user));
    }

    public function test_the_same_sms_is_never_sent_twice()
    {
        ['application' => $application] = $this->application();
        $seeker = $this->subscribe($application->user, NotificationTopic::StatusUpdates, email: false);
        $notification = new ApplicationStatusUpdated($application);

        $seeker->notify($notification);
        $seeker->notify($notification);

        $this->assertSame(1, SmsLog::count());
    }

    public function test_switched_off_templates_are_not_sent()
    {
        ['application' => $application] = $this->application();
        $seeker = $this->subscribe($application->user, NotificationTopic::StatusUpdates, email: false);
        SmsTemplate::for(SmsTemplateKey::ApplicationStatus)->update(['is_active' => false]);

        $seeker->notify(new ApplicationStatusUpdated($application));

        $this->assertSame(0, SmsLog::count());
        $this->assertSame(1, $seeker->notifications()->count());
    }

    public function test_gateway_failures_are_logged()
    {
        $this->app->instance(SmsGateway::class, new class implements SmsGateway
        {
            public function send(string $mobileNumber, string $message): SmsResult
            {
                return SmsResult::failed('Insufficient credits');
            }

            public function name(): string
            {
                return 'fake';
            }
        });
        ['application' => $application] = $this->application();
        $seeker = $this->subscribe($application->user, NotificationTopic::StatusUpdates, email: false);

        $seeker->notify(new ApplicationStatusUpdated($application->fresh()));

        $log = SmsLog::sole();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Insufficient credits', $log->error);
        $this->assertSame('fake', $log->provider);
    }

    public function test_messages_are_capped_at_two_sms()
    {
        $template = SmsTemplate::for(SmsTemplateKey::Announcement);

        $text = $template->render(['title' => 'Notice', 'message' => str_repeat('word ', 200), 'name' => 'Ana']);

        $this->assertLessThanOrEqual(SmsTemplate::MAX_LENGTH, mb_strlen($text));
        $this->assertGreaterThan(SmsTemplate::MAX_LENGTH - 5, mb_strlen($text));
        $this->assertStringEndsWith('...', $text);
        $this->assertSame('JoBauko: Notice.', $template->render(['title' => 'Notice', 'message' => null, 'unknown' => 'x']));
    }

    public function test_status_notifications_link_to_the_application()
    {
        Notification::fake();
        ['staff' => $staff, 'application' => $application] = $this->application();

        $this->actingAs($staff)->post("/org/applications/{$application->id}/status", ['status' => 'under_review']);

        Notification::assertSentTo($application->user, ApplicationStatusUpdated::class, function ($n, $channels, $notifiable) use ($application) {
            return $n->toArray($notifiable)['url'] === "/applications/{$application->id}"
                && $n->application->status === ApplicationStatus::UnderReview;
        });
    }
}
