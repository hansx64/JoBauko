import { cn } from '@/lib/utils';
import { type Paginated } from '@/types';
import { Link } from '@inertiajs/react';

/**
 * Page links for a Laravel paginator. Hidden when everything fits on one page.
 */
export function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav className="flex flex-col items-center justify-between gap-3 sm:flex-row" aria-label="Pagination">
            <p className="text-muted-foreground text-sm">
                Showing {page.from}–{page.to} of {page.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {page.links.map((link, index) => {
                    const label = link.label.replace('&laquo; ', '‹ ').replace(' &raquo;', ' ›');

                    return link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm',
                                link.active ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                            )}
                            aria-current={link.active ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span key={index} className="text-muted-foreground rounded-md border px-3 py-1.5 text-sm opacity-50">
                            {label}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}
