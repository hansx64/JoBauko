import { type Option } from '@/types';

export interface OrganizationSummary {
    name: string;
    verification_status: 'pending' | 'verified' | 'rejected' | 'suspended';
    verification_label: string;
    status_reason: string | null;
    logo_url: string | null;
}

export interface OpportunityListItem {
    id: number;
    title: string;
    category: string;
    category_label: string;
    status: string;
    status_label: string;
    deadline: string | null;
    slots: number | null;
    applications_count: number;
    updated_at: string;
}

export interface OpportunityFormData {
    id: number;
    status: string;
    status_label: string;
    stored_status: string;
    suspension_reason: string | null;
    was_published: boolean;
    category: string;
    title: string;
    description: string | null;
    qualifications: string | null;
    benefits: string | null;
    application_instructions: string | null;
    min_education: string | null;
    barangay_id: number | null;
    location_text: string | null;
    is_remote: boolean;
    application_start: string | null;
    application_deadline: string | null;
    slots: number | null;
    contact_email: string | null;
    contact_number: string | null;
    requirements: string[];
    documents: { id: number; name: string; is_required: boolean }[];
    skills: string[];
    job: {
        employment_type: string;
        salary_min: string | null;
        salary_max: string | null;
        work_setup: string | null;
        min_experience_years: number | null;
    } | null;
    scholarship: {
        education_level: string;
        eligible_courses: string[];
        academic_requirements: string | null;
        financial_requirements: string | null;
    } | null;
    livelihood: {
        assistance_type: string;
        target_beneficiaries: string | null;
        assistance_provided: string | null;
        training_requirements: string | null;
        target_classifications: string[];
    } | null;
    training: {
        training_type: string;
        starts_on: string | null;
        ends_on: string | null;
        schedule: string | null;
        duration_hours: number | null;
        fee: string | null;
        certificate: string | null;
        topics: string | null;
        inclusions: string | null;
        target_participants: string | null;
        target_classifications: string[];
    } | null;
}

export interface OpportunityFormOptions {
    categories: Option[];
    educationLevels: Option[];
    employmentTypes: Option[];
    workSetups: Option[];
    assistanceTypes: Option[];
    trainingTypes: Option[];
    classifications: Option[];
}

export function formatDate(date: string | null, options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' }) {
    if (!date) return '—';

    const value = new Date(date.length === 10 ? `${date}T00:00:00` : date);
    // toLocaleDateString refuses time options (it throws on timeStyle), so use the full formatter when a time is asked for.
    const withTime = options.timeStyle !== undefined || options.hour !== undefined || options.minute !== undefined;

    return withTime ? value.toLocaleString(undefined, options) : value.toLocaleDateString(undefined, options);
}
