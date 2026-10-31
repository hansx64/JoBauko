<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use App\Support\AuditActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of the audit log. Entries cannot be changed here or
 * anywhere else (see the audit_logs triggers).
 */
class AuditLogController extends Controller
{
    /** Subject types with an admin page to open, and their labels. */
    private const SUBJECTS = [
        User::class => ['User', 'admin.users.show'],
        Organization::class => ['Organization', 'admin.organizations.show'],
        Opportunity::class => ['Opportunity', 'admin.opportunities.show'],
        Report::class => ['Listing report', 'admin.reports.show'],
        Announcement::class => ['Announcement', 'admin.announcements.show'],
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'in:'.implode(',', array_keys(AuditActions::CATEGORIES))],
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:100'],
            'organization' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with(['user' => fn ($q) => $q->withTrashed()->select('id', 'name', 'account_type'), 'organization:id,name'])
            ->when($filters['category'] ?? null, fn (Builder $q, string $c) => $q->where('action', 'like', "{$c}.%"))
            ->when($filters['action'] ?? null, fn (Builder $q, string $a) => $q->where('action', $a))
            ->when($filters['actor'] ?? null, function (Builder $q, string $actor) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $actor).'%';
                $q->whereHas('user', fn (Builder $u) => $u->withTrashed()->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when($filters['organization'] ?? null, fn (Builder $q, int $id) => $q->where('organization_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($log) => $this->row($log));

        return Inertia::render('admin/audit-logs', [
            'logs' => $logs,
            'filters' => [
                'category' => $filters['category'] ?? null,
                'action' => $filters['action'] ?? null,
                'actor' => $filters['actor'] ?? '',
                'organization' => isset($filters['organization']) ? (int) $filters['organization'] : null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'options' => [
                'categories' => array_map(fn (string $value, string $label) => ['value' => $value, 'label' => $label], array_keys(AuditActions::CATEGORIES), AuditActions::CATEGORIES),
                'actions' => AuditActions::options(),
                'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AuditLog $log): array
    {
        [$subjectLabel, $subjectRoute] = self::SUBJECTS[$log->auditable_type] ?? [$log->auditable_type ? class_basename($log->auditable_type) : null, null];

        return [
            'id' => $log->id,
            'at' => $log->created_at->toIso8601String(),
            'action' => $log->action,
            'action_label' => AuditActions::label($log->action),
            'category' => AuditActions::category($log->action),
            'actor' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'account_type' => $log->user->account_type->value] : null,
            'organization' => $log->organization ? ['id' => $log->organization->id, 'name' => $log->organization->name] : null,
            'subject' => $subjectLabel ? [
                'label' => $subjectLabel,
                'id' => $log->auditable_id,
                'url' => $subjectRoute && $log->auditable_id ? route($subjectRoute, $log->auditable_id, false) : null,
            ] : null,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
        ];
    }
}
