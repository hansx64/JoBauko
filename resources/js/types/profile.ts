import { type Option } from '@/types';

export interface PersonalInfo {
    classification: string;
    date_of_birth: string | null;
    gender: string | null;
    address_line: string | null;
    highest_education: string | null;
    current_education_status: string | null;
    summary: string | null;
}

export interface EducationEntry {
    id: number;
    level: string;
    level_label: string;
    school: string;
    course: string | null;
    year_graduated: number | null;
    is_current: boolean;
}

export interface ExperienceEntry {
    id: number;
    position: string;
    company: string;
    industry: string | null;
    start_date: string;
    end_date: string | null;
    description: string | null;
}

export interface CertificationEntry {
    id: number;
    name: string;
    issuer: string | null;
    issued_at: string | null;
    expires_at: string | null;
}

export interface TrainingEntry {
    id: number;
    title: string;
    provider: string | null;
    completed_at: string | null;
    hours: number | null;
}

export interface Interest {
    category: string;
    field: string | null;
}

export interface ResumeSummary {
    id: number;
    original_name: string;
    size_bytes: number;
    is_pdf: boolean;
    is_generated: boolean;
    uploaded_at: string;
    extraction_status: 'pending' | 'extracted' | 'failed' | 'reviewed';
    extraction_label: string;
    extraction_error: string | null;
}

export interface CompletionSection {
    key: string;
    label: string;
    weight: number;
    complete: boolean;
    /** Must be complete before the seeker can apply to opportunities. */
    required: boolean;
    /** Required only when applying to jobs (the resume). */
    jobs_only: boolean;
    hint: string;
}

export interface Completion {
    percent: number;
    sections: CompletionSection[];
}

export interface ProfileOptions {
    classifications: Option[];
    genders: Option[];
    educationLevels: Option[];
    educationStatuses: Option[];
    interestCategories: Option[];
}
