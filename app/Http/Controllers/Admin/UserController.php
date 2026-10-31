<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\ApplicationStatus;
use App\Enums\Classification;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Accounts\AccountStatusService;
use App\Services\Audit\AuditLogger;
use App\Services\Profiles\ProfileCompletion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $type = AccountType::tryFrom((string) $request->query('type'));
        $classification = Classification::tryFrom((string) $request->query('classification'));
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;
        $barangay = $request->integer('barangay') ?: null;
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->with(['applicantProfile:id,user_id,classification', 'barangay:id,name', 'organizationMembership.organization:id,name'])
            ->when($type, fn (Builder $q) => $q->where('account_type', $type))
            ->when($classification, fn (Builder $q) => $q->whereHas('applicantProfile', fn (Builder $p) => $p->where('classification', $classification)))
            ->when($status, fn (Builder $q) => $q->where('is_active', $status === 'active'))
            ->when($barangay, fn (Builder $q) => $q->where('barangay_id', $barangay))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('mobile_number', 'like', $like));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'account_type' => $user->account_type->value,
                'account_label' => $user->account_type->label(),
                'detail' => $user->applicantProfile?->classification?->label() ?? $user->organizationMembership?->organization?->name,
                'barangay' => $user->barangay?->name,
                'is_active' => $user->is_active,
                'registered_at' => $user->created_at->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'type' => $type?->value ?? 'all',
                'classification' => $classification?->value,
                'status' => $status,
                'barangay' => $barangay,
                'q' => $search,
            ],
            'counts' => User::query()->selectRaw('account_type, count(*) as total')->groupBy('account_type')->pluck('total', 'account_type'),
            'options' => [
                'types' => AccountType::options(),
                'classifications' => Classification::options(),
                'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function show(Request $request, User $user, ProfileCompletion $completion, AuditLogger $audit): Response
    {
        $user->load(['barangay', 'applicantProfile', 'organizationMembership.organization', 'consents' => fn ($q) => $q->latest('granted_at')]);

        // The page shows personal data, so each view is on record.
        $audit->log('user.viewed', $user);

        return Inertia::render('admin/users/show', [
            'account' => $this->account($user),
            'seeker' => $user->isSeeker() ? $this->seekerSummary($user, $completion) : null,
            'membership' => $user->organizationMembership ? [
                'organization_id' => $user->organizationMembership->organization_id,
                'organization' => $user->organizationMembership->organization?->name,
                'role' => $user->organizationMembership->role->label(),
                'is_active' => $user->organizationMembership->is_active,
            ] : null,
            'consents' => $user->consents->map(fn (UserConsent $c) => [
                'id' => $c->id,
                'type' => $c->type->label(),
                'version' => $c->version,
                'granted_at' => $c->granted_at->toIso8601String(),
                'withdrawn_at' => $c->withdrawn_at?->toIso8601String(),
            ]),
            'history' => AuditLog::query()
                ->where('auditable_type', $user->getMorphClass())
                ->where('auditable_id', $user->id)
                ->whereIn('action', ['user.deactivated', 'user.reactivated'])
                ->with('user:id,name')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'by' => $log->user?->name,
                    'reason' => $log->new_values['reason'] ?? null,
                    'at' => $log->created_at->toIso8601String(),
                ]),
            'isSelf' => $request->user()->is($user),
        ]);
    }

    public function deactivate(Request $request, User $user, AccountStatusService $accounts): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:255']]);

        $accounts->deactivate($user, $request->user(), $validated['reason']);

        return back()->with('success', "{$user->name}’s account has been deactivated.");
    }

    public function reactivate(User $user, AccountStatusService $accounts): RedirectResponse
    {
        $accounts->reactivate($user);

        return back()->with('success', "{$user->name}’s account is active again.");
    }

    /**
     * @return array<string, mixed>
     */
    private function account(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'mobile_number' => $user->mobile_number,
            'mobile_verified' => $user->hasVerifiedMobile(),
            'account_type' => $user->account_type->value,
            'account_label' => $user->account_type->label(),
            'barangay' => $user->barangay?->name,
            'is_active' => $user->is_active,
            'deactivated_at' => $user->deactivated_at?->toIso8601String(),
            'deactivation_reason' => $user->deactivation_reason,
            'registered_at' => $user->created_at->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }

    /**
     * What an administrator needs to support a seeker, without the full
     * profile: that stays between the seeker and the organizations they
     * apply to.
     *
     * @return array<string, mixed>
     */
    private function seekerSummary(User $user, ProfileCompletion $completion): array
    {
        $byStatus = $user->applications()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'classification' => $user->applicantProfile?->classification?->label(),
            'profile_percent' => $completion->for($user)['percent'],
            'has_resume' => $user->currentResume()->exists(),
            'applications_total' => (int) $byStatus->sum(),
            'applications_by_status' => collect(ApplicationStatus::cases())
                ->map(fn (ApplicationStatus $s) => ['status' => $s->value, 'label' => $s->label(), 'count' => (int) ($byStatus[$s->value] ?? 0)])
                ->filter(fn (array $row) => $row['count'] > 0)
                ->values(),
            'recent_applications' => $user->applications()
                ->with(['opportunity' => fn ($q) => $q->withTrashed()->with('organization:id,name')])
                ->latest('submitted_at')
                ->limit(5)
                ->get()
                ->map(fn (Application $a) => [
                    'id' => $a->id,
                    'opportunity' => $a->opportunity->title,
                    'organization' => $a->opportunity->organization?->name,
                    'status' => $a->status->value,
                    'status_label' => $a->status->label(),
                    'submitted_at' => $a->submitted_at->toIso8601String(),
                ]),
        ];
    }
}
