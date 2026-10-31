<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Notification channel for notifications that define toSms(). Who receives
 * SMS is decided earlier, in NotificationChannels.
 */
class SmsChannel
{
    public function __construct(private readonly SmsSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if ($message instanceof SmsMessage) {
            $this->sender->send($notifiable, $message);
        }
    }
}
