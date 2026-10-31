<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the applicant that their application moved to a new status. Email
 * and SMS delivery, by notification preference, is added in Phase 9.
 */
class ApplicationStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(public readonly Application $application, public readonly ?string $remarks = null) {}

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
        return [
            'application_id' => $this->application->id,
            'opportunity_id' => $this->application->opportunity_id,
            'status' => $this->application->status->value,
            'remarks' => $this->remarks,
            'message' => "Your application for {$this->application->opportunity->title} is now {$this->application->status->label()}.",
        ];
    }
}
