import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

/**
 * Shows the success or error message flashed by the last action. Validation
 * errors are shown next to the fields they belong to instead.
 */
export function FlashMessage() {
    const { flash } = usePage<SharedData>().props;
    const message = flash.error ?? flash.success;
    const isError = Boolean(flash.error);
    const [visible, setVisible] = useState(Boolean(message));

    useEffect(() => {
        setVisible(Boolean(message));
    }, [message]);

    if (!visible || !message) {
        return null;
    }

    const Icon = isError ? CircleAlert : CircleCheck;

    return (
        <div className="px-4 pt-4 md:px-6" role={isError ? 'alert' : 'status'}>
            <div
                className={cn(
                    'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm',
                    isError
                        ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100',
                )}
            >
                <Icon className="mt-0.5 h-4 w-4 shrink-0" />
                <p className="flex-1">{message}</p>
                <button type="button" onClick={() => setVisible(false)} className="opacity-70 hover:opacity-100" aria-label="Dismiss">
                    <X className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}
