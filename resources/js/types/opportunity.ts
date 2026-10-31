export interface OpportunityMatch {
    score: number;
    reasons: string[];
    gaps?: string[];
}

export interface OpportunityCardData {
    id: number;
    slug: string;
    title: string;
    category: 'job' | 'scholarship' | 'livelihood';
    category_label: string;
    organization: { name: string; logo_url: string | null };
    location: string;
    deadline: string | null;
    days_left: number | null;
    status: string;
    status_label: string;
    accepting: boolean;
    summary: string;
    employment_type: string | null;
    salary: string | null;
    slots: number | null;
    match: OpportunityMatch | null;
    saved: boolean;
}
