import { type OpportunityCardData } from '@/types/opportunity';

export type ApplicationStatus =
    | 'submitted'
    | 'under_review'
    | 'shortlisted'
    | 'for_interview'
    | 'for_further_requirements'
    | 'approved'
    | 'rejected'
    | 'withdrawn';

export interface ApplicationSummary {
    id: number;
    status: ApplicationStatus;
    status_label: string;
    submitted_at: string;
    updated_at: string | null;
}

export interface ApplicationListItem extends ApplicationSummary {
    opportunity: OpportunityCardData;
}

export interface ApplicationDocumentItem {
    id: number;
    label: string;
    original_name: string;
    size_bytes: number;
    uploaded_at: string | null;
    added_later: boolean;
    url: string;
    preview_url: string | null;
}

export interface ApplicationHistoryEntry {
    status: ApplicationStatus;
    label: string;
    remarks: string | null;
    by_applicant: boolean;
    at: string;
}

export interface ApplicationDetails extends ApplicationSummary {
    status_description: string;
    cover_letter: string | null;
    withdrawal_reason: string | null;
    can_withdraw: boolean;
    accepts_documents: boolean;
    resume: { original_name: string; url: string; preview_url: string | null } | null;
    documents: ApplicationDocumentItem[];
    history: ApplicationHistoryEntry[];
}

export function formatFileSize(bytes: number) {
    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function formatDateTime(date: string) {
    return new Date(date).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}
