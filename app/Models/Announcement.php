<?php

namespace App\Models;

use App\Enums\AnnouncementStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A message from the municipal administrators to a chosen audience.
 *
 * @property int $id
 * @property int|null $created_by
 * @property string $title
 * @property string $body
 * @property array{account_types: list<string>, classifications: list<string>, barangays: list<int>} $audience
 * @property list<string> $channels
 * @property AnnouncementStatus $status
 * @property int $recipient_count
 * @property CarbonInterface|null $sending_started_at
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface $created_at
 */
class Announcement extends Model
{
    /** Channels an announcement can use; in-app is always included. */
    public const CHANNELS = ['in_app', 'email', 'sms'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'audience',
        'channels',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'channels' => 'array',
            'status' => AnnouncementStatus::class,
            'sending_started_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return HasMany<SmsLog, $this>
     */
    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    /**
     * @return HasMany<EmailLog, $this>
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function isDraft(): bool
    {
        return $this->status === AnnouncementStatus::Draft;
    }

    public function uses(string $channel): bool
    {
        return $channel === 'in_app' || in_array($channel, $this->channels, true);
    }
}
