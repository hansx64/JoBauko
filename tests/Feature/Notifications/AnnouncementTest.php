<?php

namespace Tests\Feature\Notifications;

use App\Enums\AnnouncementStatus;
use App\Enums\Classification;
use App\Enums\NotificationTopic;
use App\Enums\OrganizationRole;
use App\Models\Announcement;
use App\Models\Barangay;
use App\Models\EmailLog;
use App\Models\Organization;
use App\Models\SmsLog;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SubscribesUsers;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase, SubscribesUsers;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Job fair on Friday',
            'body' => "The municipal job fair is at the Bauko gym.\n\nBring your resume.",
            'audience' => ['account_types' => ['seeker'], 'classifications' => [], 'barangays' => []],
            'channels' => ['email', 'sms'],
        ], $overrides);
    }

    public function test_an_announcement_is_drafted_then_reviewed_before_sending()
    {
        $admin = User::factory()->admin()->create();
        $this->subscribe(User::factory()->seeker()->create(), NotificationTopic::Announcements);
        User::factory()->seeker()->create();
        User::factory()->seeker()->unverified()->create();

        $this->actingAs($admin)->post('/admin/announcements', $this->payload())->assertSessionHasNoErrors();

        $announcement = Announcement::sole();
        $this->assertSame(AnnouncementStatus::Draft, $announcement->status);
        $this->assertSame(['in_app', 'email', 'sms'], $announcement->channels);

        $this->get("/admin/announcements/{$announcement->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/announcements/show')
                ->where('reach', ['total' => 2, 'email' => 1, 'sms' => 1])
                ->where('audienceSummary.0', 'Opportunity Seekers, all classifications; all barangays'));
    }

    public function test_sending_delivers_by_each_recipients_choices()
    {
        $admin = User::factory()->admin()->create();
        $subscribed = $this->subscribe(User::factory()->seeker()->create(), NotificationTopic::Announcements);
        $quiet = User::factory()->seeker()->create();
        $inactive = User::factory()->seeker()->inactive()->create();

        $this->actingAs($admin)->post('/admin/announcements', $this->payload());
        $announcement = Announcement::sole();

        $this->post("/admin/announcements/{$announcement->id}/send")->assertSessionHasNoErrors();

        $announcement->refresh();
        $this->assertSame(AnnouncementStatus::Sent, $announcement->status);
        $this->assertSame(2, $announcement->recipient_count);
        $this->assertSame(1, $subscribed->notifications()->where('type', AnnouncementPublished::class)->count());
        $this->assertSame(1, $quiet->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());

        $this->assertSame($subscribed->id, SmsLog::sole()->user_id);
        $this->assertSame($announcement->id, SmsLog::sole()->announcement_id);
        $this->assertSame('JoBauko: Job fair on Friday. The municipal job fair is at the Bauko gym. Bring your resume.', SmsLog::sole()->message);
        $this->assertSame($announcement->id, EmailLog::sole()->announcement_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.sent', 'auditable_id' => $announcement->id]);

        $this->get("/admin/announcements/{$announcement->id}")
            ->assertInertia(fn ($page) => $page->where('reach', null)->where('delivery.sms.sent', 1)->where('delivery.email.sent', 1));
    }

    public function test_audiences_narrow_by_classification_and_barangay()
    {
        $this->seed(LocationSeeder::class);
        [$here, $there] = Barangay::orderBy('id')->limit(2)->pluck('id')->all();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->seeker()->create(['barangay_id' => $here]);
        $student->applicantProfile->update(['classification' => Classification::Student]);
        $elsewhere = User::factory()->seeker()->create(['barangay_id' => $there]);
        $elsewhere->applicantProfile->update(['classification' => Classification::Student]);
        $staff = User::factory()->organization()->create();
        Organization::factory()->verified()->withMember(OrganizationRole::Admin, $staff)->create();

        $this->actingAs($admin)->post('/admin/announcements', $this->payload([
            'channels' => [],
            'audience' => ['account_types' => ['seeker', 'organization'], 'classifications' => ['student'], 'barangays' => [$here]],
        ]));
        $this->post('/admin/announcements/'.Announcement::sole()->id.'/send');

        $this->assertSame(1, $student->notifications()->count());
        $this->assertSame(0, $elsewhere->notifications()->count());
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(0, EmailLog::count());
    }

    public function test_sent_announcements_are_final()
    {
        $admin = User::factory()->admin()->create();
        User::factory()->seeker()->create();
        $this->actingAs($admin)->post('/admin/announcements', $this->payload());
        $announcement = Announcement::sole();
        $this->post("/admin/announcements/{$announcement->id}/send");

        $this->post("/admin/announcements/{$announcement->id}/send")->assertSessionHasErrors('announcement');
        $this->put("/admin/announcements/{$announcement->id}", $this->payload(['title' => 'Changed']))->assertSessionHasErrors('announcement');
        $this->delete("/admin/announcements/{$announcement->id}")->assertSessionHasErrors('announcement');
        $this->get("/admin/announcements/{$announcement->id}/edit")->assertRedirect("/admin/announcements/{$announcement->id}");

        $this->assertSame('Job fair on Friday', $announcement->refresh()->title);
    }

    public function test_drafts_can_be_edited_and_deleted()
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/announcements', $this->payload());
        $announcement = Announcement::sole();

        $this->get("/admin/announcements/{$announcement->id}/edit")->assertInertia(fn ($page) => $page->where('announcement.channels', ['email', 'sms']));
        $this->put("/admin/announcements/{$announcement->id}", $this->payload(['title' => 'Job fair moved to Saturday']))->assertSessionHasNoErrors();
        $this->assertSame('Job fair moved to Saturday', $announcement->refresh()->title);

        $this->delete("/admin/announcements/{$announcement->id}")->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_input_is_validated_and_empty_audiences_cannot_be_sent()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/announcements', $this->payload(['audience' => ['account_types' => []], 'channels' => ['fax']]))
            ->assertSessionHasErrors(['audience.account_types', 'channels.0']);

        // No organization personnel exist yet.
        $this->post('/admin/announcements', $this->payload(['audience' => ['account_types' => ['organization'], 'classifications' => [], 'barangays' => []]]))
            ->assertSessionHasNoErrors();
        $this->post('/admin/announcements/'.Announcement::sole()->id.'/send')->assertSessionHasErrors('announcement');
        $this->assertSame(AnnouncementStatus::Draft, Announcement::sole()->status);
    }

    public function test_only_admins_manage_announcements()
    {
        $this->actingAs(User::factory()->seeker()->create())->get('/admin/announcements')->assertForbidden();
        $this->post('/admin/announcements', $this->payload())->assertForbidden();
    }
}
