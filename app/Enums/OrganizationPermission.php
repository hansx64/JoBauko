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
}
