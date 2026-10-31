<?php

namespace App\Notifications\Contracts;

use App\Enums\NotificationTopic;

/**
 * A notification that belongs to a topic the user can choose channels for.
 */
interface HasTopic
{
    public function topic(): NotificationTopic;
}
