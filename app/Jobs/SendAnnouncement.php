<?php

namespace App\Jobs;

use App\Enums\AnnouncementStatus;
use App\Listeners\LogNotificationEmail;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Services\Notifications\AnnouncementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers an announcement to its audience in chunks. One recipient's
 * failure is logged and does not stop the rest.
 */
class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    /** Retrying could send twice; failures are recorded per recipient instead. */
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $announcementId) {}

    public function handle(): void
    {
        $announcement = Announcement::find($this->announcementId);

        if ($announcement === null || $announcement->status !== AnnouncementStatus::Sending) {
            return;
        }

        $notification = new AnnouncementPublished($announcement);
        $count = 0;

        AnnouncementService::recipients($announcement->audience)
            ->with('notificationPreferences')
            ->chunkById(200, function (Collection $users) use ($notification, &$count) {
                /** @var User $user */
                foreach ($users as $user) {
                    try {
                        $user->notify($notification);
                    } catch (Throwable $e) {
                        Log::warning("Announcement {$notification->announcement->id} to user {$user->id} failed: {$e->getMessage()}");
                        LogNotificationEmail::record($user, $notification, 'failed', $e->getMessage());
                    }
                    $count++;
                }
            });

        $announcement->forceFill([
            'status' => AnnouncementStatus::Sent,
            'recipient_count' => $count,
            'sent_at' => now(),
        ])->save();
    }
}
