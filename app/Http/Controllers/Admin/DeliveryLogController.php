<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationTopic;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\SmsLog;
use App\Support\MobileNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What JoBauko sent by SMS and email, and whether it was delivered to the
 * provider.
 */
class DeliveryLogController extends Controller
{
    private const STATUSES = ['sent', 'failed', 'queued'];

    public function index(Request $request): Response
    {
        $channel = $request->query('channel') === 'email' ? 'email' : 'sms';
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : null;
        $topic = (string) $request->query('topic') ?: null;

        $query = ($channel === 'sms' ? SmsLog::query() : EmailLog::query())
            ->with('user:id,name')
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->when($topic, fn (Builder $q) => $q->where('topic', $topic));

        $logs = $query->latest('id')->paginate(30)->withQueryString()->through(fn (SmsLog|EmailLog $log) => [
            'id' => $log->id,
            'recipient' => $log->user?->name,
            'user_id' => $log->user_id,
            'address' => $log instanceof SmsLog ? MobileNumber::mask($log->mobile_number) : $log->email,
            'content' => $log instanceof SmsLog ? $log->message : $log->subject,
            'topic' => $log->topic,
            'status' => $log->status,
            'error' => $log->error,
            'created_at' => $log->created_at->toIso8601String(),
        ]);

        $model = $channel === 'sms' ? SmsLog::class : EmailLog::class;

        return Inertia::render('admin/delivery-logs', [
            'logs' => $logs,
            'filters' => ['channel' => $channel, 'status' => $status, 'topic' => $topic],
            'counts' => $model::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'topics' => [
                ...NotificationTopic::options(),
                ['value' => 'verification', 'label' => 'Mobile verification'],
                ['value' => 'account', 'label' => 'Account and organization'],
            ],
        ]);
    }
}
