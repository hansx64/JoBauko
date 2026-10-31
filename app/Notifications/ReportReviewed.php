<?php

namespace App\Notifications;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Lets a reporter know their report was looked at. The administrator's
 * notes are internal and are not included.
 */
class ReportReviewed extends Notification
{
    use Queueable;

    public function __construct(public readonly Report $report, public readonly ?string $opportunityTitle) {}

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
        $subject = $this->opportunityTitle ? "“{$this->opportunityTitle}”" : 'an opportunity';

        return [
            'report_id' => $this->report->id,
            'status' => $this->report->status->value,
            'message' => $this->report->status === ReportStatus::Dismissed
                ? "We reviewed your report about {$subject} and found no violation of the JoBauko rules. Thank you for reporting."
                : "We reviewed your report about {$subject} and took action. Thank you for reporting.",
        ];
    }
}
