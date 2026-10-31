<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Organization-side management of opportunities. Opportunities of other
 * organizations respond as "not found" so their existence isn't revealed.
 */
class OpportunityPolicy
{
    public function create(User $user): Response
    {
        $membership = $user->organizationMembership;

        if (! $membership?->hasPermission(OrganizationPermission::ManageOpportunities)) {
            return Response::deny('You do not have permission to manage opportunities.');
        }

        return $membership->organization->isSuspended()
            ? Response::deny('Your organization is suspended.')
            : Response::allow();
    }

    public function manage(User $user, Opportunity $opportunity): Response
    {
        if ((int) $user->organizationMembership?->organization_id !== (int) $opportunity->organization_id) {
            return Response::denyAsNotFound();
        }

        return $this->create($user);
    }
}
