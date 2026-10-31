<?php

namespace App\Notifications;

use App\Enums\NotificationTopic;
use App\Enums\SmsTemplateKey;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\Contracts\HasTopic;
use App\Services\Notifications\NotificationChannels;
use App\Services\Sms\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminds a seeker that a saved opportunity they haven't applied to closes
 * soon.
 */
class DeadlineReminder extends Notification implements HasTopic
{
    use Queueable;

    public function __construct(public readonly Opportunity $opportunity) {}

    public function topic(): NotificationTopic
    {
        return NotificationTopic::Deadlines;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? NotificationChannels::for($notifiable, $this->topic()) : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Closing soon: {$this->opportunity->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Applications for {$this->opportunity->title}, which you saved, close on {$this->deadline()}.")
            ->line('You have not applied yet.')
            ->action('View opportunity', route('opportunities.show', $this->opportunity->slug));
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage(
            SmsTemplateKey::DeadlineReminder,
            ['name' => strtok($notifiable->name, ' ') ?: $notifiable->name, 'opportunity' => $this->opportunity->title, 'deadline' => $this->deadline()],
            $this->topic(),
            "deadline:{$notifiable->id}:{$this->opportunity->id}:{$this->opportunity->application_deadline?->toDateString()}",
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'opportunity_id' => $this->opportunity->id,
            'message' => "Applications for {$this->opportunity->title} close on {$this->deadline()}. You saved it but haven't applied yet.",
            'url' => route('opportunities.show', $this->opportunity->slug, false),
        ];
    }

    private function deadline(): string
    {
        return $this->opportunity->application_deadline?->format('M j, Y') ?? '';
    }
}
