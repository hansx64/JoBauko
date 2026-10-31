import { router } from '@inertiajs/react';
import { Bookmark, BookmarkCheck } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function SaveButton({ opportunityId, saved, size = 'icon' }: { opportunityId: number; saved: boolean; size?: 'icon' | 'default' }) {
    const [processing, setProcessing] = useState(false);

    const toggle = () => {
        const options = { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) };

        if (saved) {
            router.delete(route('seeker.saved.destroy', opportunityId), options);
        } else {
            router.post(route('seeker.saved.store', opportunityId), {}, options);
        }
    };

    const Icon = saved ? BookmarkCheck : Bookmark;
    const label = saved ? 'Saved' : 'Save';

    return (
        <Button
            type="button"
            variant={saved ? 'secondary' : 'outline'}
            size={size}
            onClick={toggle}
            disabled={processing}
            aria-pressed={saved}
            aria-label={saved ? 'Remove from saved opportunities' : 'Save opportunity'}
            title={saved ? 'Remove from saved' : 'Save for later'}
        >
            <Icon className={cn('h-4 w-4', saved && 'text-primary')} />
            {size === 'default' && label}
        </Button>
    );
}
