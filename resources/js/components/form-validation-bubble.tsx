import { CircleAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

type Field = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

interface Bubble {
    field: Field;
    anchor: HTMLElement;
    message: string;
}

/**
 * Replaces the browser's own validation popups ("Please fill out this field")
 * with a message in the site's style. Mounted once for the whole app: when a
 * form is submitted with an invalid field, the browser's popup is cancelled
 * and this one appears under the first invalid field until it is corrected.
 */
export function FormValidationBubble() {
    const [bubble, setBubble] = useState<Bubble | null>(null);
    const [position, setPosition] = useState<{ top: number; left: number; width: number } | null>(null);
    const pending = useRef(false);

    // Catch every invalid event (they do not bubble, so listen while capturing).
    useEffect(() => {
        const onInvalid = (event: Event) => {
            const field = event.target as Field;
            event.preventDefault();

            // A submit reports every invalid field; show only the first.
            if (pending.current) return;
            pending.current = true;
            setTimeout(() => (pending.current = false), 0);

            const anchor = visibleAnchor(field);
            setBubble({ field, anchor, message: messageFor(field) });
            focusField(field, anchor);
        };

        document.addEventListener('invalid', onInvalid, true);
        return () => document.removeEventListener('invalid', onInvalid, true);
    }, []);

    // Follow the field while the page scrolls or resizes; close once corrected.
    useEffect(() => {
        if (!bubble) return;

        const place = () => {
            if (!bubble.anchor.isConnected) return setBubble(null);
            const rect = bubble.anchor.getBoundingClientRect();
            setPosition({ top: rect.bottom + 6, left: rect.left, width: rect.width });
        };
        const update = () => {
            if (bubble.field.validity.valid) setBubble(null);
            else setBubble((current) => current && { ...current, message: messageFor(current.field) });
        };
        const close = () => setBubble(null);
        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && close();

        place();
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);
        bubble.field.addEventListener('input', update);
        bubble.field.addEventListener('change', update);
        bubble.field.addEventListener('blur', close);
        document.addEventListener('keydown', onKey);

        return () => {
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
            bubble.field.removeEventListener('input', update);
            bubble.field.removeEventListener('change', update);
            bubble.field.removeEventListener('blur', close);
            document.removeEventListener('keydown', onKey);
        };
    }, [bubble]);

    if (!bubble || !position) return null;

    return createPortal(
        <div
            role="alert"
            style={{ top: position.top, left: position.left, maxWidth: Math.max(position.width, 240) }}
            className="bg-popover text-popover-foreground animate-in fade-in-0 zoom-in-95 fixed z-[100] flex items-start gap-2 rounded-md border border-red-200 px-3 py-2 text-sm shadow-md dark:border-red-900/60"
        >
            <span
                aria-hidden="true"
                className="bg-popover absolute -top-1.5 left-4 h-3 w-3 rotate-45 border-t border-l border-red-200 dark:border-red-900/60"
            />
            <CircleAlert className="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" aria-hidden="true" />
            <span>{bubble.message}</span>
        </div>,
        document.body,
    );
}

/** Our own wording for each kind of problem, instead of the browser's. */
function messageFor(field: Field): string {
    const v = field.validity;
    const type = field instanceof HTMLInputElement ? field.type : field.tagName.toLowerCase();

    if (v.customError) return field.validationMessage;
    if (v.valueMissing) {
        if (type === 'checkbox') return 'Please tick this box to continue.';
        if (type === 'radio' || type === 'select') return 'Please choose an option.';
        if (type === 'file') return 'Please choose a file to upload.';
        return 'This field is required.';
    }
    if (v.typeMismatch) {
        if (type === 'email') return 'Enter a valid email address, like name@example.com.';
        if (type === 'url') return 'Enter a full web address, starting with https://';
        return 'Check the format of this field.';
    }
    if (v.badInput) return type === 'number' ? 'Enter a number.' : 'Enter a valid value.';
    if (v.tooShort && 'minLength' in field) return `Use at least ${field.minLength} characters (now ${field.value.length}).`;
    if (v.tooLong && 'maxLength' in field) return `Use at most ${field.maxLength} characters.`;
    if (v.rangeUnderflow && field instanceof HTMLInputElement) return `Enter ${field.min} or more.`;
    if (v.rangeOverflow && field instanceof HTMLInputElement) return `Enter ${field.max} or less.`;
    if (v.stepMismatch) return 'Enter a valid value.';
    if (v.patternMismatch) return field.title || 'Check the format of this field.';

    return 'Please check this field.';
}

/**
 * Some components keep a hidden native field for the form (for example custom
 * checkboxes and selects); point at the visible control next to it instead.
 */
function visibleAnchor(field: Field): HTMLElement {
    const candidates = [field, field.previousElementSibling, field.parentElement];

    for (const el of candidates) {
        if (el instanceof HTMLElement) {
            const rect = el.getBoundingClientRect();
            if (rect.width > 1 && rect.height > 1) return el;
        }
    }

    return field;
}

function focusField(field: Field, anchor: HTMLElement) {
    const target = anchor !== field && anchor.tabIndex >= 0 ? anchor : field;
    target.focus({ preventScroll: true });
    anchor.scrollIntoView({ block: 'center', behavior: 'smooth' });
}
