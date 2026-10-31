<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Verification documents: administrators, and the organization's own
     * active personnel.
     */
    public function viewDocuments(User $user, Organization $organization): bool
    {
        return $user->isAdmin() || $this->isActiveMember($user, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization)
            && $user->organizationMembership->hasPermission(OrganizationPermission::ManageOrganization);
    }

    private function isActiveMember(User $user, Organization $organization): bool
    {
        $membership = $user->organizationMembership;

        return $membership !== null
            && $membership->is_active
            && (int) $membership->organization_id === (int) $organization->id;
    }
}
