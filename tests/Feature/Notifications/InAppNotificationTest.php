<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationTopic;
use App\Models\Application;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ApplicationStatusUpdated;
use App\Notifications\ReportReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bell_shows_unread_notifications()
    {
        $application = Application::factory()->create();
        $application->user->notify(new ApplicationStatusUpdated($application));

        $this->actingAs($application->user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unread', 1)
                ->has('notifications.recent', 1)
                ->where('notifications.recent.0.has_link', true)
                ->where('notifications.recent.0.read', false));
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_link()
    {
        $application = Application::factory()->create();
        $application->user->notify(new ApplicationStatusUpdated($application));
        $notification = $application->user->notifications()->sole();

        $this->actingAs($application->user)
            ->get("/notifications/{$notification->id}")
            ->assertRedirect("/applications/{$application->id}");

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_notifications_without_a_link_return_to_the_list()
    {
        $report = Report::factory()->create();
        $report->reporter->notify(new ReportReviewed($report, 'Some job'));
        $notification = $report->reporter->notifications()->sole();

        $this->actingAs($report->reporter)->get("/notifications/{$notification->id}")->assertRedirect(route('notifications.index'));
    }

    public function test_links_outside_the_portal_are_not_followed()
    {
        $user = User::factory()->seeker()->create();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ApplicationStatusUpdated::class,
            'data' => ['message' => 'x', 'url' => '//evil.example/phish'],
        ]);

        $this->actingAs($user)->get("/notifications/{$notification->id}")->assertRedirect(route('notifications.index'));
    }

    public function test_users_cannot_open_each_others_notifications()
    {
        $application = Application::factory()->create();
        $application->user->notify(new ApplicationStatusUpdated($application));
        $notification = $application->user->notifications()->sole();

        $this->actingAs(User::factory()->seeker()->create())->get("/notifications/{$notification->id}")->assertNotFound();
        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_the_list_filters_unread_and_can_mark_all_read()
    {
        $application = Application::factory()->create();
        $user = $application->user;
        $user->notify(new ApplicationStatusUpdated($application));
        $user->notify(new ApplicationStatusUpdated($application));
        $user->notifications()->first()->markAsRead();

        $this->actingAs($user)
            ->get('/notifications?filter=unread')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('notifications/index')->has('notifications.data', 1)->where('unreadCount', 1));

        $this->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_guests_get_no_notification_data()
    {
        $this->get('/')->assertInertia(fn ($page) => $page->where('notifications', null));
        $this->get('/notifications')->assertRedirect(route('login'));
        $this->assertSame(NotificationTopic::StatusUpdates, (new ApplicationStatusUpdated(Application::factory()->create()))->topic());
    }
}
