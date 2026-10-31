<?php

namespace App\Http\Controllers\Organization\Concerns;

use App\Models\Organization;
use Illuminate\Http\Request;

trait ResolvesOrganization
{
    /**
     * The organization of the signed-in member. EnsureOrganizationMember
     * guarantees it exists on organization routes.
     */
    protected function organization(Request $request): Organization
    {
        return $request->user()->organizationMembership->organization;
    }

    /**
     * @return array{name: string, verification_status: string, verification_label: string, status_reason: ?string, logo_url: ?string}
     */
    protected function organizationSummary(Organization $organization): array
    {
        return [
            'name' => $organization->name,
            'verification_status' => $organization->verification_status->value,
            'verification_label' => $organization->verification_status->label(),
            'status_reason' => $organization->status_reason,
            'logo_url' => $organization->logoUrl(),
        ];
    }
}
