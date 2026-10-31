<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationTopic;
use App\Enums\OpportunityStatus;
use App\Jobs\NotifyMatchingSeekers;
use App\Models\Application;
use App\Models\Opportunity;
use App\Models\SmsLog;
use App\Models\User;
use App\Notifications\DeadlineReminder;
use App\Notifications\NewOpportunityMatch;
use App\Services\Opportunities\OpportunityService;
use App\Services\Recommendation\MatchResult;
use App\Services\Recommendation\MatchScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SubscribesUsers;
use Tests\TestCase;

class AutomaticNoticesTest extends TestCase
{
    use RefreshDatabase, SubscribesUsers;

    public function test_seekers_are_reminded_once_about_saved_opportunities_closing_soon()
    {
        $closing = Opportunity::factory()->job()->published()->create(['application_deadline' => today()->addDays(2)]);
        $later = Opportunity::factory()->job()->published()->create(['application_deadline' => today()->addDays(20)]);
        $saver = $this->subscribe(User::factory()->seeker()->create(), NotificationTopic::Deadlines, email: false);
        $applied = User::factory()->seeker()->create();
        $saver->savedOpportunities()->attach([$closing->id, $later->id]);
        $applied->savedOpportunities()->attach($closing->id);
        Application::factory()->for($closing)->for($applied)->create();

        $this->artisan('notifications:deadline-reminders')->expectsOutput('Sent 1 deadline reminder.')->assertSuccessful();
        $this->artisan('notifications:deadline-reminders')->expectsOutput('Sent 0 deadline reminders.')->assertSuccessful();

        $this->assertSame(1, $saver->notifications()->where('type', DeadlineReminder::class)->count());
        $this->assertSame(0, $applied->notifications()->count());
        $this->assertStringContainsString($closing->title, SmsLog::sole()->message);
    }

    public function test_publishing_queues_alerts_for_matching_seekers()
    {
        Bus::fake([NotifyMatchingSeekers::class]);
        $draft = Opportunity::factory()->job()->create();

        app(OpportunityService::class)->publish($draft);

        Bus::assertDispatched(NotifyMatchingSeekers::class, fn ($job) => $job->opportunityId === $draft->id);
    }

    public function test_only_opted_in_strong_matches_are_alerted_once()
    {
        Notification::fake();
        $job = Opportunity::factory()->job()->published()->create();
        $optedIn = $this->subscribe(User::factory()->seeker()->create(), NotificationTopic::Jobs, sms: false);
        $notOptedIn = User::factory()->seeker()->create();
        $wrongTopic = $this->subscribe(User::factory()->seeker()->create(), NotificationTopic::Scholarships, sms: false);

        $this->mock(MatchScorer::class, fn ($mock) => $mock->shouldReceive('score')->andReturnUsing(
            fn () => new MatchResult(NotifyMatchingSeekers::ALERT_THRESHOLD, [], []),
        ));

        (new NotifyMatchingSeekers($job->id))->handle(app(MatchScorer::class));
        (new NotifyMatchingSeekers($job->id))->handle(app(MatchScorer::class));

        Notification::assertSentToTimes($optedIn, NewOpportunityMatch::class, 1);
        Notification::assertNotSentTo([$notOptedIn, $wrongTopic], NewOpportunityMatch::class);
    }

    public function test_weak_matches_and_closed_listings_are_not_alerted()
    {
        Notification::fake();
        $job = Opportunity::factory()->job()->published()->create();
        $seeker = $this->subscribe(User::factory()->seeker()->create(), NotificationTopic::Jobs, sms: false);

        $this->mock(MatchScorer::class, fn ($mock) => $mock->shouldReceive('score')->andReturnUsing(
            fn () => new MatchResult(NotifyMatchingSeekers::ALERT_THRESHOLD - 1, [], []),
        ));
        (new NotifyMatchingSeekers($job->id))->handle(app(MatchScorer::class));

        $job->forceFill(['status' => OpportunityStatus::Closed])->save();
        (new NotifyMatchingSeekers($job->id))->handle(app(MatchScorer::class));

        Notification::assertNotSentTo($seeker, NewOpportunityMatch::class);
    }
}
