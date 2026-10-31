<?php

namespace App\Notifications;

use App\Enums\AssistanceType;
use App\Enums\NotificationTopic;
use App\Enums\OpportunityCategory;
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
 * Alerts a seeker to a newly published opportunity that matches their
 * profile, in a topic they opted into.
 */
class NewOpportunityMatch extends Notification implements HasTopic
{
    use Queueable;

    public function __construct(public readonly Opportunity $opportunity, public readonly int $score) {}

    /**
     * The preference topic an opportunity belongs to.
     */
    public static function topicFor(Opportunity $opportunity): NotificationTopic
    {
        return match ($opportunity->category) {
            OpportunityCategory::Job => NotificationTopic::Jobs,
            OpportunityCategory::Scholarship => NotificationTopic::Scholarships,
            OpportunityCategory::Livelihood => $opportunity->livelihoodDetail?->assistance_type === AssistanceType::SkillsTraining
                ? NotificationTopic::Training
                : NotificationTopic::Livelihood,
        };
    }

    public function topic(): NotificationTopic
    {
        return self::topicFor($this->opportunity);
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
            ->subject("New {$this->opportunity->category->label()} opportunity: {$this->opportunity->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->opportunity->organization->name} published {$this->opportunity->title}. It is a {$this->score}% match for your profile.")
            ->line("Applications close on {$this->deadline()}.")
            ->action('View opportunity', route('opportunities.show', $this->opportunity->slug))
            ->line('You receive these because you chose this topic in your notification settings.');
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage(
            SmsTemplateKey::NewOpportunity,
            [
                'name' => strtok($notifiable->name, ' ') ?: $notifiable->name,
                'opportunity' => $this->opportunity->title,
                'organization' => $this->opportunity->organization->name,
                'deadline' => $this->deadline(),
            ],
            $this->topic(),
            "new_opportunity:{$notifiable->id}:{$this->opportunity->id}",
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'opportunity_id' => $this->opportunity->id,
            'score' => $this->score,
            'message' => "New for you: {$this->opportunity->title} by {$this->opportunity->organization->name} ({$this->score}% match).",
            'url' => route('opportunities.show', $this->opportunity->slug, false),
        ];
    }

    private function deadline(): string
    {
        return $this->opportunity->application_deadline?->format('M j, Y') ?? '';
    }
}
