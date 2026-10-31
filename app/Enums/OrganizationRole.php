<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Roles of personnel within one organization. Distinct from AccountType
 * (platform area) and Classification (seeker profile attribute).
 */
enum OrganizationRole: string
{
    use HasOptions;

    case Admin = 'admin';
    case RecruitmentOfficer = 'recruitment_officer';
    case Reviewer = 'reviewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Organization Administrator',
            self::RecruitmentOfficer => 'Recruitment Officer',
            self::Reviewer => 'Application Reviewer',
        };
    }

    /**
     * @return list<OrganizationPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => OrganizationPermission::cases(),
            self::RecruitmentOfficer => [
                OrganizationPermission::ManageOpportunities,
                OrganizationPermission::ViewApplications,
                OrganizationPermission::AddRemarks,
                OrganizationPermission::ReviewApplications,
                OrganizationPermission::DecideApplications,
            ],
            self::Reviewer => [
                OrganizationPermission::ViewApplications,
                OrganizationPermission::AddRemarks,
                OrganizationPermission::ReviewApplications,
            ],
        };
    }

    public function can(OrganizationPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
