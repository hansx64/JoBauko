<?php

namespace App\Notifications;

use App\Enums\NotificationTopic;
use App\Enums\SmsTemplateKey;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Contracts\HasTopic;
use App\Services\Notifications\NotificationChannels;
use App\Services\Sms\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementPublished extends Notification implements HasTopic
{
    use Queueable;

    public function __construct(public readonly Announcement $announcement) {}

    public function topic(): NotificationTopic
    {
        return NotificationTopic::Announcements;
    }

    /**
     * Seekers get the channels they chose for announcements. Personnel and
     * administrators have no topic preferences, so they get email when the
     * announcement uses it. SMS always needs the seeker's own choice and
     * consent.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $allowed = array_values(array_filter([
            NotificationChannels::IN_APP,
            $this->announcement->uses('email') ? NotificationChannels::EMAIL : null,
            $this->announcement->uses('sms') ? NotificationChannels::SMS : null,
        ]));

        if (! $notifiable instanceof User) {
            return [NotificationChannels::IN_APP];
        }

        if ($notifiable->isSeeker()) {
            return NotificationChannels::for($notifiable, $this->topic(), $allowed);
        }

        return $this->announcement->uses('email') && $notifiable->email_verified_at !== null
            ? [NotificationChannels::IN_APP, NotificationChannels::EMAIL]
            : [NotificationChannels::IN_APP];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->announcement->title)
            ->greeting("Hello {$notifiable->name},");

        foreach (preg_split('/\R{2,}/', trim($this->announcement->body)) ?: [] as $paragraph) {
            $message->line($paragraph);
        }

        return $message
            ->action('Open JoBauko', route('notifications.index'))
            ->salutation('— Municipality of Bauko, through JoBauko');
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage(
            SmsTemplateKey::Announcement,
            ['name' => strtok($notifiable->name, ' ') ?: $notifiable->name, 'title' => $this->announcement->title, 'message' => $this->announcement->body],
            $this->topic(),
            "announcement:{$this->announcement->id}:{$notifiable->id}",
            $this->announcement->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'message' => "{$this->announcement->title}: ".Str::limit($this->announcement->body, 140),
            'url' => null,
        ];
    }
}
