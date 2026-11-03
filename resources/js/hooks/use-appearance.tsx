import { useCallback, useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'appearance';
const CHANGE_EVENT = 'appearance-change';

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

// Storage can be unavailable (private browsing, blocked site data); the theme then follows the system.
function savedAppearance(): Appearance {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        return 'system';
    }
}

const applyTheme = (appearance: Appearance) => {
    const isDark = appearance === 'dark' || (appearance === 'system' && mediaQuery.matches);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

export function initializeTheme() {
    applyTheme(savedAppearance());

    // Follow the device while the choice is "System".
    mediaQuery.addEventListener('change', () => applyTheme(savedAppearance()));
}

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>(savedAppearance);

    // Keep every theme switch on the page (and in other tabs) in step.
    useEffect(() => {
        const sync = () => setAppearance(savedAppearance());

        window.addEventListener(CHANGE_EVENT, sync);
        window.addEventListener('storage', sync);

        return () => {
            window.removeEventListener(CHANGE_EVENT, sync);
            window.removeEventListener('storage', sync);
        };
    }, []);

    const updateAppearance = useCallback((mode: Appearance) => {
        try {
            localStorage.setItem(STORAGE_KEY, mode);
        } catch {
            // Not saved; it still applies until the page is reloaded.
        }

        setAppearance(mode);
        applyTheme(mode);
        window.dispatchEvent(new Event(CHANGE_EVENT));
    }, []);

    return { appearance, updateAppearance };
}
