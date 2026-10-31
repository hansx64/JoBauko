<?php

namespace App\Notifications;

use App\Models\OrganizationMember;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Invites new personnel to set a password for the account an organization
 * administrator created for them.
 */
class OrganizationInvitation extends Notification
{
    use Queueable;

    public const VALID_DAYS = 3;

    public function __construct(public readonly OrganizationMember $member) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->member->organization->name;

        return (new MailMessage)
            ->subject("You've been added to {$organization} on JoBauko")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$organization} added you to its team on JoBauko as {$this->member->role->label()}.")
            ->line('Set a password to activate your account.')
            ->action('Set my password', self::url($this->member))
            ->line('This link expires in '.self::VALID_DAYS.' days. If it has expired, ask your organization administrator to send a new one.')
            ->line('If you were not expecting this, you can ignore this email.');
    }

    public static function url(OrganizationMember $member): string
    {
        return URL::temporarySignedRoute('invitations.show', now()->addDays(self::VALID_DAYS), ['user' => $member->user_id]);
    }
}
