import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CircleCheck, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Shows the success message flashed by the last action. Validation errors
 * are shown next to the fields they belong to instead.
 */
export function FlashMessage() {
    const { flash } = usePage<SharedData>().props;
    const [visible, setVisible] = useState(Boolean(flash.success));

    useEffect(() => {
        setVisible(Boolean(flash.success));
    }, [flash.success]);

    if (!visible || !flash.success) {
        return null;
    }

    return (
        <div className="px-4 pt-4 md:px-6" role="status">
            <div className="flex items-start gap-3 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-100">
                <CircleCheck className="mt-0.5 h-4 w-4 shrink-0" />
                <p className="flex-1">{flash.success}</p>
                <button type="button" onClick={() => setVisible(false)} className="opacity-70 hover:opacity-100" aria-label="Dismiss">
                    <X className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}
