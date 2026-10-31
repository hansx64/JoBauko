import { usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';

import AppLayout from '@/layouts/app-layout';
import PublicLayout from '@/layouts/public-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

/**
 * For pages open to everyone, such as browsing opportunities: signed-in
 * users keep their sidebar, visitors get the public header and footer.
 */
export default function BrowseLayout({ children, breadcrumbs }: { children: ReactNode; breadcrumbs: BreadcrumbItem[] }) {
    const { auth } = usePage<SharedData>().props;

    return auth.user ? <AppLayout breadcrumbs={breadcrumbs}>{children}</AppLayout> : <PublicLayout>{children}</PublicLayout>;
}
