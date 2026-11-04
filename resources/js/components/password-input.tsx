import { Input } from '@/components/ui/input';
import { passwordStrength, type StrengthLevel } from '@/lib/password-strength';
import { cn } from '@/lib/utils';
import { Check, Eye, EyeOff, X } from 'lucide-react';
import * as React from 'react';

type PasswordInputProps = Omit<React.ComponentProps<'input'>, 'type'> & {
    /** For choosing a new password: show the strength meter and refuse weak passwords before submitting. */
    strength?: boolean;
    /** For a confirmation field: the password it must repeat. */
    mustMatch?: string;
};

/**
 * A password field with a button on the right that shows or hides what was typed.
 * `className` applies to the wrapper, so layout classes such as margins work as on Input.
 */
const PasswordInput = React.forwardRef<HTMLInputElement, PasswordInputProps>(
    ({ className, disabled, strength = false, mustMatch, ...props }, ref) => {
        const [visible, setVisible] = React.useState(false);
        const inputRef = React.useRef<HTMLInputElement | null>(null);
        const meterId = React.useId();

        const value = typeof props.value === 'string' ? props.value : '';
        const result = React.useMemo(() => (strength ? passwordStrength(value) : null), [strength, value]);

        // A weak or mismatched password blocks the form like any other invalid field.
        const problem =
            value === ''
                ? ''
                : result && !result.acceptable
                  ? (result.problem ?? 'Choose a stronger password: use at least 10 characters with letters and numbers.')
                  : mustMatch !== undefined && value !== mustMatch
                    ? 'The passwords do not match.'
                    : '';

        React.useEffect(() => {
            inputRef.current?.setCustomValidity(problem);
        }, [problem]);

        const setRefs = (node: HTMLInputElement | null) => {
            inputRef.current = node;
            if (typeof ref === 'function') ref(node);
            else if (ref) ref.current = node;
        };

        return (
            <div className={cn('space-y-2', className)}>
                <div className="relative">
                    <Input
                        ref={setRefs}
                        type={visible ? 'text' : 'password'}
                        disabled={disabled}
                        className="pr-10"
                        aria-describedby={strength ? meterId : undefined}
                        {...props}
                    />
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

                {result && (
                    <div id={meterId} className="space-y-2" aria-live="polite">
                        <div className="flex items-center gap-3">
                            <div className="flex flex-1 gap-1" aria-hidden="true">
                                {[1, 2, 3, 4].map((segment) => (
                                    <div
                                        key={segment}
                                        className={cn(
                                            'h-1.5 flex-1 rounded-full transition-colors',
                                            segment <= result.level ? BAR[result.level] : 'bg-muted',
                                        )}
                                    />
                                ))}
                            </div>
                            <span className={cn('min-w-16 text-right text-xs font-medium', TEXT[result.level])}>
                                {result.level > 0 ? result.label : ''}
                                <span className="sr-only">{result.level > 0 ? ' password' : ''}</span>
                            </span>
                        </div>

                        <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            {result.checks.map((check) => (
                                <li
                                    key={check.label}
                                    className={cn('flex items-center gap-1.5', check.met ? 'text-primary' : 'text-muted-foreground')}
                                >
                                    {check.met ? <Check className="h-3.5 w-3.5 shrink-0" /> : <X className="h-3.5 w-3.5 shrink-0" />}
                                    {check.label}
                                    <span className="sr-only">{check.met ? '(done)' : '(missing)'}</span>
                                </li>
                            ))}
                        </ul>

                        {result.problem && <p className="text-destructive text-xs">{result.problem}</p>}
                    </div>
                )}
            </div>
        );
    },
);

PasswordInput.displayName = 'PasswordInput';

const BAR: Record<StrengthLevel, string> = {
    0: 'bg-muted',
    1: 'bg-red-500',
    2: 'bg-amber-500',
    3: 'bg-lime-600 dark:bg-lime-500',
    4: 'bg-primary',
};

const TEXT: Record<StrengthLevel, string> = {
    0: 'text-muted-foreground',
    1: 'text-red-600 dark:text-red-400',
    2: 'text-amber-700 dark:text-amber-400',
    3: 'text-lime-700 dark:text-lime-400',
    4: 'text-primary',
};

export { PasswordInput };
