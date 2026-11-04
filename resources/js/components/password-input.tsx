import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';

/**
 * A password field with a button on the right that shows or hides what was typed.
 * `className` applies to the wrapper, so layout classes such as margins work as on Input.
 */
const PasswordInput = React.forwardRef<HTMLInputElement, Omit<React.ComponentProps<'input'>, 'type'>>(({ className, disabled, ...props }, ref) => {
    const [visible, setVisible] = React.useState(false);

    return (
        <div className={cn('relative', className)}>
            <Input ref={ref} type={visible ? 'text' : 'password'} disabled={disabled} className="pr-10" {...props} />
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                disabled={disabled}
                aria-label={visible ? 'Hide password' : 'Show password'}
                aria-pressed={visible}
                title={visible ? 'Hide password' : 'Show password'}
                className="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md focus-visible:ring-2 focus-visible:outline-hidden disabled:pointer-events-none disabled:opacity-50"
            >
                {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
        </div>
    );
});

PasswordInput.displayName = 'PasswordInput';

export { PasswordInput };
