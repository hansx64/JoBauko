export interface ReportRow {
    id: number;
    reason: string;
    reason_label: string;
    details: string | null;
    status: 'open' | 'action_taken' | 'dismissed';
    status_label: string;
    outcome: 'opportunity_suspended' | 'resolved' | 'no_violation' | null;
    reporter: string | null;
    resolver: string | null;
    resolved_at: string | null;
    created_at: string;
    target: { id: number; title: string; organization: string | null } | null;
}

export const outcomeLabels: Record<NonNullable<ReportRow['outcome']>, string> = {
    opportunity_suspended: 'Opportunity suspended',
    resolved: 'Resolved',
    no_violation: 'No violation found',
};
