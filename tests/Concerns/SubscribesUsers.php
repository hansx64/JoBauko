<?php

namespace Tests\Concerns;

use App\Enums\ConsentType;
use App\Enums\NotificationTopic;
use App\Models\User;
use App\Services\Privacy\ConsentRecorder;

trait SubscribesUsers
{
    /**
     * Give a user a topic preference and, for SMS, consent and a verified number.
     */
    protected function subscribe(User $user, NotificationTopic $topic, bool $email = true, bool $sms = true): User
    {
        $user->notificationPreferences()->updateOrCreate(['topic' => $topic], ['email' => $email, 'sms' => $sms, 'in_app' => true, 'source' => 'user']);

        if ($sms) {
            $user->forceFill(['mobile_verified_at' => now()])->save();
            app(ConsentRecorder::class)->grant($user, ConsentType::Sms);
        }

        return $user->refresh();
    }
}
