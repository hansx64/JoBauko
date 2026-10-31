<?php

namespace App\Services\Notifications;

use App\Enums\ConsentType;
use App\Enums\NotificationTopic;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;

/**
 * Decides how a person is told about something. In-app is always on.
 * Email needs the topic switched on for email and a verified address. SMS
 * also needs SMS consent under the Data Privacy Act and a verified mobile.
 */
class NotificationChannels
{
    public const IN_APP = 'database';

    public const EMAIL = 'mail';

    public const SMS = SmsChannel::class;

    /**
     * @param  list<string>|null  $allowed  limit to these channels (e.g. an announcement sent without SMS)
     * @return list<string>
     */
    public static function for(User $user, NotificationTopic $topic, ?array $allowed = null): array
    {
        $preference = $user->relationLoaded('notificationPreferences')
            ? $user->notificationPreferences->first(fn (NotificationPreference $p) => $p->topic === $topic)
            : $user->notificationPreferences()->where('topic', $topic)->first();

        $channels = [self::IN_APP];

        if ($preference?->email && $user->email_verified_at !== null) {
            $channels[] = self::EMAIL;
        }

        if ($preference?->sms && $user->hasVerifiedMobile() && $user->hasActiveConsent(ConsentType::Sms)) {
            $channels[] = self::SMS;
        }

        return $allowed === null ? $channels : array_values(array_intersect($channels, $allowed));
    }
}
