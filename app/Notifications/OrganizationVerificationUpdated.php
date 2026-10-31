<?php

namespace App\Notifications;

use App\Enums\VerificationStatus;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationVerificationUpdated extends Notification
{
    use Queueable;

    public function __construct(public readonly Organization $organization) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->organization->verification_status;

        $message = (new MailMessage)
            ->subject("{$this->organization->name}: {$status->label()}")
            ->greeting("Hello {$notifiable->name},");

        $message = match ($status) {
            VerificationStatus::Verified => $message->line("{$this->organization->name} has been verified. You can now publish opportunities on JoBauko."),
            VerificationStatus::Rejected => $message->line("The verification request for {$this->organization->name} was not approved.")
                ->line("Reason: {$this->organization->status_reason}")
                ->line('You can update your organization details and documents, then resubmit.'),
            VerificationStatus::Suspended => $message->line("{$this->organization->name} has been suspended. Its opportunities are hidden from the public.")
                ->line("Reason: {$this->organization->status_reason}"),
            VerificationStatus::Pending => $message->line("{$this->organization->name} is awaiting verification."),
        };

        return $message->action('Open JoBauko', route('org.dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organization->id,
            'status' => $this->organization->verification_status->value,
            'reason' => $this->organization->status_reason,
            'url' => route('org.dashboard', absolute: false),
            'message' => "{$this->organization->name}: {$this->organization->verification_status->label()}",
        ];
    }
}
