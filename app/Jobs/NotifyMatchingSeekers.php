<?php

namespace App\Jobs;

use App\Enums\AccountType;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\NewOpportunityMatch;
use App\Services\Notifications\NotificationDedupe;
use App\Services\Recommendation\MatchScorer;
use App\Services\Recommendation\SeekerSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tells seekers about a newly published opportunity when it is a strong
 * match and they opted into its topic by email or SMS. Seekers who chose
 * neither are not alerted; they find it in their recommendations.
 */
class NotifyMatchingSeekers implements ShouldQueue
{
    use Queueable;

    /** Minimum match score for an alert; stricter than the recommendation list. */
    public const ALERT_THRESHOLD = 60;

    public function __construct(public readonly int $opportunityId) {}

    public function handle(MatchScorer $scorer): void
    {
        $opportunity = Opportunity::query()->visible()->open()->with(['organization', 'livelihoodDetail', 'trainingDetail', 'skills'])->find($this->opportunityId);

        if ($opportunity === null) {
            return;
        }

        $topic = NewOpportunityMatch::topicFor($opportunity);

        User::query()
            ->where('account_type', AccountType::Seeker)
            ->where('is_active', true)
            ->whereHas('notificationPreferences', fn (Builder $q) => $q->where('topic', $topic)->where(fn (Builder $c) => $c->where('email', true)->orWhere('sms', true)))
            ->whereDoesntHave('applications', fn (Builder $q) => $q->withTrashed()->where('opportunity_id', $opportunity->id))
            ->with('notificationPreferences')
            ->chunkById(200, function (Collection $users) use ($scorer, $opportunity) {
                /** @var User $user */
                foreach ($users as $user) {
                    $score = $scorer->score(SeekerSnapshot::fromUser($user), $opportunity)->score;

                    if ($score >= self::ALERT_THRESHOLD && NotificationDedupe::claim("new_opportunity:{$user->id}:{$opportunity->id}")) {
                        $user->notify(new NewOpportunityMatch($opportunity, $score));
                    }
                }
            });
    }
}
