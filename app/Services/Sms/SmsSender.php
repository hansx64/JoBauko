<?php

namespace App\Services\Sms;

use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Renders, sends and logs template SMS. Every attempt is written to sms_logs,
 * including failures, so administrators can see what was delivered.
 */
class SmsSender
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(User $user, SmsMessage $message): ?SmsLog
    {
        $template = SmsTemplate::for($message->template);

        if (! $template->is_active || $user->mobile_number === null) {
            return null;
        }

        try {
            // Claiming the dedupe key first means a repeated send stops here.
            $log = SmsLog::create([
                'user_id' => $user->id,
                'sms_template_id' => $template->id,
                'announcement_id' => $message->announcementId,
                'mobile_number' => $user->mobile_number,
                'message' => $template->render($message->values),
                'topic' => $message->topic->value,
                'dedupe_key' => $message->dedupeKey,
                'status' => 'queued',
                'provider' => $this->gateway->name(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        try {
            $result = $this->gateway->send($log->mobile_number, $log->message);
        } catch (Throwable $e) {
            $result = SmsResult::failed(substr($e->getMessage(), 0, 500));
        }

        $log->forceFill([
            'status' => $result->successful ? 'sent' : 'failed',
            'provider_message_id' => $result->providerMessageId,
            'error' => $result->error,
            'sent_at' => $result->successful ? now() : null,
        ])->save();

        return $log;
    }
}
