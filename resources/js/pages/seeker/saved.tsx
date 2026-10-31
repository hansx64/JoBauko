import { Head, Link } from '@inertiajs/react';
import { Bookmark } from 'lucide-react';

import { OpportunityCard } from '@/components/opportunities/opportunity-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type OpportunityCardData } from '@/types/opportunity';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Saved opportunities', href: '/saved' }];

export default function Saved({ opportunities, unavailableCount }: { opportunities: OpportunityCardData[]; unavailableCount: number }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Saved opportunities" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Saved opportunities</h1>
                    <p className="text-muted-foreground">Opportunities you bookmarked to review or apply to later.</p>
                </div>

                {unavailableCount > 0 && (
                    <p className="text-muted-foreground text-sm">
                        {unavailableCount} saved {unavailableCount === 1 ? 'opportunity is' : 'opportunities are'} no longer available and{' '}
                        {unavailableCount === 1 ? 'is' : 'are'} hidden.
                    </p>
                )}

                {opportunities.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border p-10 text-center">
                        <Bookmark className="text-muted-foreground h-8 w-8" />
                        <p className="font-medium">No saved opportunities yet</p>
                        <p className="text-muted-foreground text-sm">Use the bookmark button on any opportunity to keep it here.</p>
                        <Button asChild>
                            <Link href={route('opportunities.index')}>Browse opportunities</Link>
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {opportunities.map((opportunity) => (
                            <OpportunityCard key={opportunity.id} opportunity={opportunity} showSave />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
