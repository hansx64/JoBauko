import { Link } from '@inertiajs/react';
import { Ban, Clock, ShieldAlert } from 'lucide-react';

import { type OrganizationSummary } from '@/types/organization';

/**
 * Explains what an unverified organization can and cannot do yet.
 */
export function VerificationBanner({ organization }: { organization: OrganizationSummary }) {
    if (organization.verification_status === 'verified') {
        return null;
    }

    const content = {
        pending: {
            icon: Clock,
            className: 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100',
            title: 'Waiting for verification',
            body: 'The JoBauko administrator is reviewing your organization. You can prepare opportunities as drafts; they can be published once you are verified.',
        },
        rejected: {
            icon: ShieldAlert,
            className: 'border-red-300 bg-red-50 text-red-950 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100',
            title: 'Verification was not approved',
            body: 'Update your organization details or upload corrected documents to resubmit.',
        },
        suspended: {
            icon: Ban,
            className: 'border-red-300 bg-red-50 text-red-950 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100',
            title: 'Organization suspended',
            body: 'Your opportunities are hidden from the public and cannot be changed. Contact the JoBauko administrator.',
        },
    }[organization.verification_status];

    const Icon = content.icon;

    return (
        <div className={`flex gap-3 rounded-lg border px-4 py-3 text-sm ${content.className}`}>
            <Icon className="mt-0.5 h-5 w-5 shrink-0" />
            <div className="space-y-1">
                <p className="font-medium">{content.title}</p>
                <p>{content.body}</p>
                {organization.status_reason && <p>Reason: {organization.status_reason}</p>}
                {organization.verification_status === 'rejected' && (
                    <Link href={route('org.profile.edit')} className="font-medium underline underline-offset-4">
                        Update organization details
                    </Link>
                )}
            </div>
        </div>
    );
}
