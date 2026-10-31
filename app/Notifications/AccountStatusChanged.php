<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email only: a deactivated person can't sign in to read an in-app message.
 */
class AccountStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public readonly bool $active, public readonly ?string $reason = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->greeting("Hello {$notifiable->name},");

        if ($this->active) {
            return $message->subject('Your JoBauko account has been reactivated')
                ->line('Your JoBauko account is active again. You can sign in as before.')
                ->action('Sign in', route('login'));
        }

        return $message->subject('Your JoBauko account has been deactivated')
            ->line('A JoBauko administrator deactivated your account. You will not be able to sign in until it is reactivated.')
            ->line("Reason: {$this->reason}")
            ->line('Your records have not been deleted. Contact the Bauko municipal office if you believe this is a mistake.');
    }
}
