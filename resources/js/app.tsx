import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { type SharedData } from './types';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'JoBauko';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// After signing out, Back must not show pages from the signed-in session.
// When Back/Forward restores a signed-in page from history, ask the server
// again: if the session has ended, it redirects to the sign-in page. (The
// server also sends no-store and clears encrypted history on sign-out; this
// covers plain-HTTP setups where history encryption is unavailable.)
let restoringFromHistory = false;
window.addEventListener('popstate', () => {
    restoringFromHistory = true;
});
router.on('navigate', (event) => {
    const restored = restoringFromHistory;
    restoringFromHistory = false;

    if (restored && (event.detail.page.props as unknown as SharedData).auth?.user) {
        router.reload({ only: ['auth'] });
    }
});

// Pages restored from the browser's back/forward cache skip the check above.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});
