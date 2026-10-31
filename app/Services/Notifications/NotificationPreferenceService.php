<?php

namespace App\Services\Notifications;

use App\Enums\Classification;
use App\Enums\NotificationTopic;
use App\Models\User;

class NotificationPreferenceService
{
    /**
     * Create one preference row per topic, pre-selecting the topics suggested
     * for the user's classification. Pre-selection only marks topics of
     * interest; SMS is still only sent once the user has given SMS consent.
     */
    public function applyClassificationDefaults(User $user, Classification $classification): void
    {
        $selected = [
            ...$classification->defaultNotificationTopics(),
            ...NotificationTopic::alwaysSelected(),
        ];

        foreach (NotificationTopic::cases() as $topic) {
            $isSelected = in_array($topic, $selected, true);

            $user->notificationPreferences()->updateOrCreate(
                ['topic' => $topic],
                [
                    'sms' => $isSelected,
                    'email' => $isSelected,
                    'in_app' => true,
                    'source' => 'classification_default',
                ],
            );
        }
    }
}
