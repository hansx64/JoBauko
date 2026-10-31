<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Enums\OrganizationPermission;
use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Applicants see and act on their own applications; personnel see those of
 * their own organization's opportunities. Anything else is "not found" so
 * the existence of an application is never revealed.
 */
class ApplicationPolicy
{
    public function view(User $user, Application $application): Response
    {
        if ($this->owns($user, $application) || $this->memberCan($user, $application, OrganizationPermission::ViewApplications)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function withdraw(User $user, Application $application): Response
    {
        if (! $this->owns($user, $application)) {
            return Response::denyAsNotFound();
        }

        return $application->canBeWithdrawn()
            ? Response::allow()
            : Response::deny("An application that is {$application->status->label()} can no longer be withdrawn.");
    }

    public function addDocument(User $user, Application $application): Response
    {
        if (! $this->owns($user, $application)) {
            return Response::denyAsNotFound();
        }

        return $application->acceptsAdditionalDocuments()
            ? Response::allow()
            : Response::deny('Documents can only be added when the organization asks for further requirements.');
    }

    /**
     * Reviewers may move applications along; approving or rejecting needs
     * the decision permission.
     */
    public function changeStatus(User $user, Application $application, ApplicationStatus $to): Response
    {
        if (! $this->memberCan($user, $application, OrganizationPermission::ViewApplications)) {
            return Response::denyAsNotFound();
        }

        $permission = $to->isDecision() ? OrganizationPermission::DecideApplications : OrganizationPermission::ReviewApplications;

        return $this->memberCan($user, $application, $permission)
            ? Response::allow()
            : Response::deny('Your role does not allow this status change.');
    }

    private function owns(User $user, Application $application): bool
    {
        return $user->isSeeker() && (int) $application->user_id === (int) $user->id;
    }

    private function memberCan(User $user, Application $application, OrganizationPermission $permission): bool
    {
        $membership = $user->organizationMembership;

        return $membership !== null
            && (int) $membership->organization_id === (int) $application->opportunity()->withTrashed()->value('organization_id')
            && $membership->hasPermission($permission);
    }
}
