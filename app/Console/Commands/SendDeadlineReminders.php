<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\DeadlineReminder;
use App\Services\Notifications\NotificationDedupe;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reminds seekers about saved opportunities that close in DAYS_BEFORE days
 * and that they have not applied to. Safe to run more than once a day.
 */
class SendDeadlineReminders extends Command
{
    public const DAYS_BEFORE = 3;

    protected $signature = 'notifications:deadline-reminders';

    protected $description = 'Remind seekers of saved opportunities that close soon';

    public function handle(): int
    {
        $sent = 0;

        $opportunities = Opportunity::query()
            ->visible()
            ->open()
            ->whereDate('application_deadline', '<=', today()->addDays(self::DAYS_BEFORE))
            ->with('organization')
            ->get();

        foreach ($opportunities as $opportunity) {
            User::query()
                ->where('account_type', AccountType::Seeker)
                ->where('is_active', true)
                ->whereHas('savedOpportunities', fn (Builder $q) => $q->whereKey($opportunity->id))
                ->whereDoesntHave('applications', fn (Builder $q) => $q->withTrashed()->where('opportunity_id', $opportunity->id))
                ->with('notificationPreferences')
                ->each(function (User $user) use ($opportunity, &$sent) {
                    if (NotificationDedupe::claim("deadline:{$user->id}:{$opportunity->id}:{$opportunity->application_deadline->toDateString()}")) {
                        $user->notify(new DeadlineReminder($opportunity));
                        $sent++;
                    }
                });
        }

        $this->info("Sent {$sent} deadline ".str('reminder')->plural($sent).'.');

        return self::SUCCESS;
    }
}
