<?php

namespace App\Notifications;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an organization that an administrator suspended or reinstated one
 * of its opportunities.
 */
class OpportunityModerated extends Notification
{
    use Queueable;

    public function __construct(public readonly Opportunity $opportunity) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->suspended() ? "Opportunity suspended: {$this->opportunity->title}" : "Opportunity reinstated: {$this->opportunity->title}")
            ->greeting("Hello {$notifiable->name},");

        $message = $this->suspended()
            ? $message->line("A JoBauko administrator suspended “{$this->opportunity->title}”. It is hidden from the public and can't be edited.")
                ->line("Reason: {$this->opportunity->suspension_reason}")
                ->line('Applications already received stay available to your personnel. Reply to the municipal office if you believe this is a mistake.')
            : $message->line("“{$this->opportunity->title}” has been reinstated and is {$this->opportunity->displayStatusLabel()} again.");

        return $message->action('View opportunity', route('org.opportunities.edit', $this->opportunity));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'opportunity_id' => $this->opportunity->id,
            'status' => $this->opportunity->status->value,
            'reason' => $this->opportunity->suspension_reason,
            'url' => route('org.opportunities.edit', $this->opportunity, false),
            'message' => $this->suspended()
                ? "“{$this->opportunity->title}” was suspended by an administrator."
                : "“{$this->opportunity->title}” was reinstated.",
        ];
    }

    private function suspended(): bool
    {
        return $this->opportunity->status === OpportunityStatus::Suspended;
    }
}
