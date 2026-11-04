import commonWords from './common-passwords.json';

/**
 * Mirrors the server's password rules (Password::defaults in AppServiceProvider
 * and App\Rules\NotCommonPassword), so the meter never accepts a password the
 * server refuses. Production also refuses passwords found in known data breaches,
 * which only the server can check.
 */
export const MIN_PASSWORD_LENGTH = 10;

export type StrengthLevel = 0 | 1 | 2 | 3 | 4;

export interface PasswordCheck {
    label: string;
    met: boolean;
}

export interface PasswordStrength {
    level: StrengthLevel;
    label: string;
    acceptable: boolean;
    checks: PasswordCheck[];
    /** Why an acceptable-looking password is still refused, if it is. */
    problem: string | null;
}

const words = new Set<string>(commonWords);

export function isCommonPassword(password: string): boolean {
    const lower = password.toLowerCase();
    if (words.has(lower.replace(/[^a-z]/g, ''))) {
        return true;
    }

    const characters = Array.from(lower);
    if (new Set(characters).size <= 3) {
        return true;
    }

    let steps = 0;
    for (let i = 1; i < characters.length; i++) {
        if (Math.abs(characters[i].codePointAt(0)! - characters[i - 1].codePointAt(0)!) === 1) {
            steps++;
        }
    }

    return steps >= characters.length - 2;
}

const LABELS: Record<StrengthLevel, string> = {
    0: '',
    1: 'Too weak',
    2: 'Fair',
    3: 'Good',
    4: 'Strong',
};

export function passwordStrength(password: string): PasswordStrength {
    const checks: PasswordCheck[] = [
        { label: `At least ${MIN_PASSWORD_LENGTH} characters`, met: password.length >= MIN_PASSWORD_LENGTH },
        { label: 'A letter', met: /\p{L}/u.test(password) },
        { label: 'A number', met: /\p{N}/u.test(password) },
    ];

    if (password === '') {
        return { level: 0, label: LABELS[0], acceptable: false, checks, problem: null };
    }

    const common = isCommonPassword(password);
    const acceptable = checks.every((c) => c.met) && !common;
    const problem = common ? 'Too easy to guess. Avoid common words like "password" and runs like "12345".' : null;

    if (!acceptable) {
        return { level: 1, label: LABELS[1], acceptable, checks, problem };
    }

    // Extra points beyond the minimum: length, mixed case and symbols.
    let points = 0;
    if (password.length >= 14) points++;
    if (password.length >= 18) points++;
    if (/\p{Ll}/u.test(password) && /\p{Lu}/u.test(password)) points++;
    if (/[^\p{L}\p{N}]/u.test(password)) points++;

    const level: StrengthLevel = points >= 3 ? 4 : points >= 1 ? 3 : 2;

    return { level, label: LABELS[level], acceptable, checks, problem };
}
