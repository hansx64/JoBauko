<?php

namespace App\Services\Sms;

use App\Enums\NotificationTopic;
use App\Enums\SmsTemplateKey;

/**
 * An SMS to render from a template. The dedupe key makes sure the same
 * message is never sent to the same person twice.
 */
final readonly class SmsMessage
{
    /**
     * @param  array<string, string|null>  $values
     */
    public function __construct(
        public SmsTemplateKey $template,
        public array $values,
        public NotificationTopic $topic,
        public ?string $dedupeKey = null,
        public ?int $announcementId = null,
    ) {}
}
