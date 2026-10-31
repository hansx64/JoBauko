<?php

namespace App\Support;

/**
 * Readable names for the actions written to the audit log, grouped the way
 * the audit log viewer filters them.
 */
final class AuditActions
{
    public const CATEGORIES = [
        'user' => 'User accounts',
        'organization' => 'Organization verification',
        'personnel' => 'Organization personnel',
        'opportunity' => 'Opportunities',
        'application' => 'Applications',
        'report' => 'Reports and exports',
        'announcement' => 'Announcements',
        'sms_template' => 'SMS templates',
    ];

    private const LABELS = [
        'user.viewed' => 'Viewed a user’s account',
        'user.deactivated' => 'Deactivated an account',
        'user.reactivated' => 'Reactivated an account',
        'organization.registered' => 'Registered an organization',
        'organization.updated' => 'Updated organization details',
        'organization.resubmitted' => 'Resubmitted for verification',
        'organization.verified' => 'Verified an organization',
        'organization.rejected' => 'Rejected a verification request',
        'organization.suspended' => 'Suspended an organization',
        'organization.reinstated' => 'Reinstated an organization',
        'personnel.invited' => 'Invited personnel',
        'personnel.invitation_resent' => 'Resent an invitation',
        'personnel.updated' => 'Changed personnel role or access',
        'opportunity.created' => 'Created an opportunity',
        'opportunity.updated' => 'Edited an opportunity',
        'opportunity.published' => 'Published an opportunity',
        'opportunity.unpublished' => 'Moved an opportunity back to draft',
        'opportunity.closed' => 'Closed an opportunity',
        'opportunity.reopened' => 'Reopened an opportunity',
        'opportunity.deadline_extended' => 'Extended a deadline',
        'opportunity.archived' => 'Archived an opportunity',
        'opportunity.suspended' => 'Suspended an opportunity',
        'opportunity.reinstated' => 'Reinstated an opportunity',
        'application.submitted' => 'Submitted an application',
        'application.status_changed' => 'Changed an application’s status',
        'application.withdrawn' => 'Withdrew an application',
        'application.document_added' => 'Added an application document',
        'application.remark_added' => 'Added an internal remark',
        'application.viewed' => 'Viewed an application',
        'report.submitted' => 'Reported a listing',
        'report.reviewed' => 'Decided on a listing report',
        'report.viewed' => 'Viewed a report with personal data',
        'report.exported' => 'Exported a report',
        'announcement.sent' => 'Sent an announcement',
        'sms_template.updated' => 'Edited an SMS template',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    public static function category(string $action): string
    {
        return strtok($action, '.') ?: $action;
    }

    /**
     * @return list<array{value: string, label: string, category: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $action, string $label) => ['value' => $action, 'label' => $label, 'category' => self::category($action)],
            array_keys(self::LABELS),
            self::LABELS,
        );
    }
}
