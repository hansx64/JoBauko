<?php

namespace App\Listeners;

use App\Models\EmailLog;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Notifications\Contracts\HasTopic;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;

/**
 * Writes each notification email to email_logs, so administrators can see
 * what was sent to whom.
 */
class LogNotificationEmail
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail' || ! $event->notifiable instanceof User) {
            return;
        }

        self::record($event->notifiable, $event->notification, 'sent');
    }

    /**
     * Also used to record emails that failed to send.
     */
    public static function record(User $user, Notification $notification, string $status, ?string $error = null): EmailLog
    {
        $subject = method_exists($notification, 'toMail') ? $notification->toMail($user)->subject : null;

        return EmailLog::create([
            'user_id' => $user->id,
            'announcement_id' => $notification instanceof AnnouncementPublished ? $notification->announcement->id : null,
            'email' => $user->email,
            'subject' => substr((string) ($subject ?? class_basename($notification)), 0, 255),
            'topic' => $notification instanceof HasTopic ? $notification->topic()->value : 'account',
            'status' => $status,
            'error' => $error === null ? null : substr($error, 0, 1000),
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
