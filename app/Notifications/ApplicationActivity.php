<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells organization personnel about something an applicant did.
 */
class ApplicationActivity extends Notification
{
    use Queueable;

    /**
     * @param  'submitted'|'withdrawn'|'documents_added'  $event
     */
    public function __construct(public readonly Application $application, public readonly string $event) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $applicant = $this->application->user->name;
        $title = $this->application->opportunity->title;

        return [
            'application_id' => $this->application->id,
            'opportunity_id' => $this->application->opportunity_id,
            'event' => $this->event,
            'message' => match ($this->event) {
                'submitted' => "New application from {$applicant} for {$title}.",
                'withdrawn' => "{$applicant} withdrew their application for {$title}.",
                'documents_added' => "{$applicant} added documents to their application for {$title}.",
            },
        ];
    }
}
