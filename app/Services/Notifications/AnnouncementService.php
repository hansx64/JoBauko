<?php

namespace App\Services\Notifications;

use App\Enums\AccountType;
use App\Enums\AnnouncementStatus;
use App\Enums\ConsentType;
use App\Enums\NotificationTopic;
use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drafting and sending administrator announcements. Classification and
 * barangay filters narrow the seeker audience only; personnel and
 * administrators are included or not as a whole.
 */
class AnnouncementService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{title: string, body: string, audience: array<string, mixed>, channels: list<string>}  $data
     */
    public function saveDraft(?Announcement $announcement, User $admin, array $data): Announcement
    {
        if ($announcement !== null && ! $announcement->isDraft()) {
            throw ValidationException::withMessages(['announcement' => 'Sent announcements cannot be changed.']);
        }

        $announcement ??= new Announcement;
        $announcement->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => self::normalizeAudience($data['audience']),
            'channels' => array_values(array_unique(['in_app', ...$data['channels']])),
        ]);
        $announcement->created_by ??= $admin->id;
        $announcement->status ??= AnnouncementStatus::Draft;
        $announcement->save();

        return $announcement;
    }

    public function send(Announcement $announcement): void
    {
        DB::transaction(function () use ($announcement) {
            $locked = Announcement::whereKey($announcement->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['announcement' => 'This announcement has already been sent.']);
            }

            if (! self::recipients($locked->audience)->exists()) {
                throw ValidationException::withMessages(['announcement' => 'No one matches this audience yet.']);
            }

            $locked->forceFill(['status' => AnnouncementStatus::Sending, 'sending_started_at' => now()])->save();

            $this->audit->log('announcement.sent', $locked, new: [
                'title' => $locked->title,
                'audience' => $locked->audience,
                'channels' => $locked->channels,
            ]);

            SendAnnouncement::dispatch($locked->id)->afterCommit();
        });
    }

    /**
     * Active accounts with a verified email address in the audience.
     *
     * @param  array<string, mixed>  $audience
     * @return Builder<User>
     */
    public static function recipients(array $audience): Builder
    {
        $audience = self::normalizeAudience($audience);

        return User::query()
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->where(function (Builder $q) use ($audience) {
                // An empty audience matches no one.
                $q->whereRaw('1 = 0');

                foreach ($audience['account_types'] as $type) {
                    $q->orWhere(fn (Builder $w) => match ($type) {
                        AccountType::Seeker->value => $w->where('account_type', AccountType::Seeker)
                            ->when($audience['classifications'], fn (Builder $s) => $s->whereHas('applicantProfile', fn (Builder $p) => $p->whereIn('classification', $audience['classifications'])))
                            ->when($audience['barangays'], fn (Builder $s) => $s->whereIn('barangay_id', $audience['barangays'])),
                        AccountType::Organization->value => $w->where('account_type', AccountType::Organization)
                            ->whereHas('organizationMembership', fn (Builder $m) => $m->where('is_active', true)),
                        default => $w->where('account_type', AccountType::Admin),
                    });
                }
            });
    }

    /**
     * How many people the announcement reaches, in total and per channel.
     * SMS and seeker email follow each seeker's announcement preference.
     *
     * @param  array<string, mixed>  $audience
     * @param  list<string>  $channels
     * @return array{total: int, email: int, sms: int}
     */
    public static function reach(array $audience, array $channels): array
    {
        $prefers = fn (string $column) => fn (Builder $q) => $q->where('topic', NotificationTopic::Announcements)->where($column, true);

        return [
            'total' => self::recipients($audience)->count(),
            'email' => in_array('email', $channels, true)
                ? self::recipients($audience)
                    ->where(fn (Builder $q) => $q->where('account_type', '!=', AccountType::Seeker)->orWhereHas('notificationPreferences', $prefers('email')))
                    ->count()
                : 0,
            'sms' => in_array('sms', $channels, true)
                ? self::recipients($audience)
                    ->where('account_type', AccountType::Seeker)
                    ->whereNotNull('mobile_verified_at')
                    ->whereHas('notificationPreferences', $prefers('sms'))
                    ->whereHas('consents', fn (Builder $c) => $c->where('type', ConsentType::Sms)->whereNull('withdrawn_at'))
                    ->count()
                : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $audience
     * @return array{account_types: list<string>, classifications: list<string>, barangays: list<int>}
     */
    public static function normalizeAudience(array $audience): array
    {
        $types = array_values(array_intersect(AccountType::values(), (array) ($audience['account_types'] ?? [])));
        $seekers = in_array(AccountType::Seeker->value, $types, true);

        return [
            'account_types' => $types,
            'classifications' => $seekers ? array_values(array_map('strval', (array) ($audience['classifications'] ?? []))) : [],
            'barangays' => $seekers ? array_values(array_map('intval', (array) ($audience['barangays'] ?? []))) : [],
        ];
    }
}
