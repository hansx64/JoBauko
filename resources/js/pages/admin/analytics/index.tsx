import { Head, Link } from '@inertiajs/react';
import { ChevronRight, FileBarChart, ShieldAlert } from 'lucide-react';

import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface ReportSummary {
    key: string;
    title: string;
    description: string;
    personal_data: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports & analytics', href: '/admin/analytics' }];

export default function AnalyticsIndex({ reports }: { reports: ReportSummary[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports & analytics" />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Reports & analytics</h1>
                    <p className="text-muted-foreground">Choose a report, set the period, then view it here or download it as PDF or Excel.</p>
                </div>

                <Card className="overflow-hidden py-0">
                    <ul className="divide-y">
                        {reports.map((report) => (
                            <li key={report.key}>
                                <Link
                                    href={route('admin.analytics.show', report.key)}
                                    className="hover:bg-muted/30 flex items-center gap-4 px-4 py-4"
                                >
                                    <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg">
                                        <FileBarChart className="h-5 w-5" />
                                    </span>
                                    <span className="grid flex-1 gap-0.5">
                                        <span className="flex flex-wrap items-center gap-2 font-medium">
                                            {report.title}
                                            {report.personal_data && (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200 ring-inset dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-900">
                                                    <ShieldAlert className="h-3 w-3" /> Personal data
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-muted-foreground text-sm">{report.description}</span>
                                    </span>
                                    <ChevronRight className="text-muted-foreground h-4 w-4 shrink-0" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </AppLayout>
    );
}
