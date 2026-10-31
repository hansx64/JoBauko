<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = VerificationStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search'));

        $organizations = Organization::query()
            ->withCount('opportunities')
            ->when($status, fn ($q) => $q->where('verification_status', $status))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            // Pending requests first, oldest first, so none wait too long.
            ->orderByRaw('verification_status = ? desc', [VerificationStatus::Pending->value])
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Organization $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'type' => $o->type->label(),
                'verification_status' => $o->verification_status->value,
                'verification_label' => $o->verification_status->label(),
                'opportunities_count' => $o->opportunities_count,
                'registered_at' => $o->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/organizations/index', [
            'organizations' => $organizations,
            'filters' => ['status' => $status?->value ?? 'all', 'search' => $search],
            'counts' => Organization::query()->selectRaw('verification_status, count(*) as total')->groupBy('verification_status')->pluck('total', 'verification_status'),
            'statuses' => VerificationStatus::options(),
        ]);
    }

    public function show(Request $request, Organization $organization): Response
    {
        $organization->load(['documents', 'barangay', 'members.user', 'verifier']);

        return Inertia::render('admin/organizations/show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'type' => $organization->type->label(),
                'official_email' => $organization->official_email,
                'contact_number' => $organization->contact_number,
                'office_address' => $organization->office_address,
                'barangay' => $organization->barangay?->name,
                'description' => $organization->description,
                'logo_url' => $organization->logoUrl(),
                'representative_name' => $organization->representative_name,
                'representative_position' => $organization->representative_position,
                'verification_status' => $organization->verification_status->value,
                'verification_label' => $organization->verification_status->label(),
                'status_reason' => $organization->status_reason,
                'verified_at' => $organization->verified_at?->toIso8601String(),
                'verified_by' => $organization->verifier?->name,
                'registered_at' => $organization->created_at->toIso8601String(),
            ],
            'documents' => $organization->documents->map(fn ($d) => [
                'id' => $d->id,
                'original_name' => $d->original_name,
                'mime_type' => $d->mime_type,
                'size_bytes' => $d->size_bytes,
                'uploaded_at' => $d->created_at->toIso8601String(),
            ]),
            'members' => $organization->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role->label(),
                'is_active' => $m->is_active,
            ]),
            'opportunityCounts' => $organization->opportunities()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'history' => AuditLog::query()
                ->where('organization_id', $organization->id)
                ->where('action', 'like', 'organization.%')
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
        ]);
    }
}
