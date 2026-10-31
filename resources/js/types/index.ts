import { LucideIcon } from 'lucide-react';

export type OrganizationPermission =
    | 'manage_organization'
    | 'manage_personnel'
    | 'manage_opportunities'
    | 'view_applications'
    | 'add_remarks'
    | 'review_applications'
    | 'decide_applications';

export interface OrganizationAccess {
    name: string;
    role: string;
    role_label: string;
    permissions: OrganizationPermission[];
}

export interface Auth {
    user: User;
    organization: OrganizationAccess | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Organization permission required to show the item. */
    permission?: OrganizationPermission;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    flash: { success: string | null };
    auth: Auth;
    [key: string]: unknown;
}

export type AccountType = 'seeker' | 'organization' | 'admin';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    mobile_number: string | null;
    mobile_verified_at: string | null;
    barangay_id: number | null;
    account_type: AccountType;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Option {
    value: string;
    label: string;
}

export interface Barangay {
    id: number;
    name: string;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}
