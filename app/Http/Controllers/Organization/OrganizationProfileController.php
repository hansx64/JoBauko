<?php

namespace App\Http\Controllers\Organization;

use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organization\Concerns\ResolvesOrganization;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Models\Barangay;
use App\Services\Audit\AuditLogger;
use App\Services\Organizations\OrganizationRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationProfileController extends Controller
{
    use ResolvesOrganization;

    public function edit(Request $request): Response
    {
        $organization = $this->organization($request)->load('documents');

        return Inertia::render('organization/profile', [
            'organization' => [
                ...$this->organizationSummary($organization),
                'id' => $organization->id,
                'type' => $organization->type->value,
                'official_email' => $organization->official_email,
                'contact_number' => $organization->contact_number,
                'office_address' => $organization->office_address,
                'barangay_id' => $organization->barangay_id,
                'description' => $organization->description,
                'representative_name' => $organization->representative_name,
                'representative_position' => $organization->representative_position,
            ],
            'documents' => $organization->documents->map(fn ($d) => [
                'id' => $d->id,
                'original_name' => $d->original_name,
                'size_bytes' => $d->size_bytes,
                'uploaded_at' => $d->created_at->toIso8601String(),
            ]),
            'canEdit' => Gate::allows('update', $organization),
            'organizationTypes' => OrganizationType::options(),
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Save details and add documents. A rejected organization that saves its
     * corrections is resubmitted for verification.
     */
    public function update(UpdateOrganizationRequest $request, OrganizationRegistrationService $registration, AuditLogger $audit): RedirectResponse
    {
        $organization = $this->organization($request);
        Gate::authorize('update', $organization);

        $resubmitted = false;

        DB::transaction(function () use ($request, $organization, $registration, $audit, &$resubmitted) {
            $organization->fill($request->safe()->except(['logo', 'documents']));

            if ($request->hasFile('logo')) {
                $old = $organization->logo_path;
                $organization->logo_path = $request->file('logo')->store('organization-logos', OrganizationRegistrationService::LOGO_DISK);
                DB::afterCommit(fn () => $old && Storage::disk(OrganizationRegistrationService::LOGO_DISK)->delete($old));
            }

            $resubmitted = $organization->verification_status === VerificationStatus::Rejected;

            if ($resubmitted) {
                $organization->verification_status = VerificationStatus::Pending;
            }

            $organization->save();
            $audit->logChanges($resubmitted ? 'organization.resubmitted' : 'organization.updated', $organization, $organization->id);

            foreach ($request->file('documents', []) as $file) {
                $registration->storeDocument($organization, $file, 'verification');
            }
        });

        return back()->with('success', $resubmitted ? 'Saved and resubmitted for verification.' : 'Organization details saved.');
    }
}
