<?php

namespace App\Notifications;

use App\Enums\NotificationTopic;
use App\Enums\SmsTemplateKey;
use App\Models\Application;
use App\Models\User;
use App\Notifications\Contracts\HasTopic;
use App\Services\Notifications\NotificationChannels;
use App\Services\Sms\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the applicant that their application moved to a new status: in the
 * app always, and by email or SMS as they chose.
 */
class ApplicationStatusUpdated extends Notification implements HasTopic
{
    use Queueable;

    public function __construct(public readonly Application $application, public readonly ?string $remarks = null) {}

    public function topic(): NotificationTopic
    {
        return NotificationTopic::StatusUpdates;
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
        $title = $this->application->opportunity->title;
        $status = $this->application->status;

        $message = (new MailMessage)
            ->subject("Your application for {$title}: {$status->label()}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your application for {$title} is now **{$status->label()}**.")
            ->line($status->description());

        if ($this->remarks) {
            $message->line("Message from {$this->application->opportunity->organization->name}: {$this->remarks}");
        }

        return $message->action('View my application', route('seeker.applications.show', $this->application));
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage(
            SmsTemplateKey::ApplicationStatus,
            [
                'name' => strtok($notifiable->name, ' ') ?: $notifiable->name,
                'opportunity' => $this->application->opportunity->title,
                'status' => $this->application->status->label(),
                'remarks' => $this->remarks,
            ],
            $this->topic(),
            "application:{$this->application->id}:status:{$this->application->status->value}:".$this->application->statusHistory()->count(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'opportunity_id' => $this->application->opportunity_id,
            'status' => $this->application->status->value,
            'remarks' => $this->remarks,
            'message' => "Your application for {$this->application->opportunity->title} is now {$this->application->status->label()}.",
            'url' => route('seeker.applications.show', $this->application, false),
        ];
    }
}
