<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case ManageOrganization = 'manage_organization';
    case ManagePersonnel = 'manage_personnel';
    case ManageOpportunities = 'manage_opportunities';
    case ViewApplications = 'view_applications';
    case AddRemarks = 'add_remarks';
    case ReviewApplications = 'review_applications';
    case DecideApplications = 'decide_applications';

    public function label(): string
    {
        return match ($this) {
            self::ManageOrganization => 'Edit the organization profile and documents',
            self::ManagePersonnel => 'Add personnel and change their roles',
            self::ManageOpportunities => 'Create, edit and publish opportunities',
            self::ViewApplications => 'View applications and applicant profiles',
            self::AddRemarks => 'Add internal remarks',
            self::ReviewApplications => 'Move applications through review',
            self::DecideApplications => 'Approve or reject applications',
        };
    }
}
