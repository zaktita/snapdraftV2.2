import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import posthog from 'posthog-js';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import { ErrorBoundary } from './components/error-boundary';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const DEFAULT_POSTHOG_HOST = 'https://us.i.posthog.com';

type PostHogConfig = { token?: string; host?: string };
type AuthUser = { id: number; email: string; name: string };

function initializePostHog(phConfig?: PostHogConfig, user?: AuthUser | null) {
    const token = phConfig?.token?.trim();
    if (!token) {
        return;
    }

    // HMR/dev reloads can attempt to initialize PostHog multiple times.
    if ((posthog as { __loaded?: boolean }).__loaded) {
        posthog.set_config({
            api_host: phConfig?.host?.trim() || DEFAULT_POSTHOG_HOST,
        });
    } else {
        posthog.init(token, {
            api_host: phConfig?.host?.trim() || DEFAULT_POSTHOG_HOST,
            person_profiles: 'identified_only',
            capture_pageview: true,
            capture_pageleave: true,
        });
    }

    if (user?.id) {
        posthog.identify(String(user.id), {
            email: user.email,
            name: user.name,
        });
        return;
    }

    posthog.reset();
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const { posthog: phConfig, auth } = (props.initialPage.props as Record<string, unknown> & {
            posthog?: PostHogConfig;
            auth?: { user?: AuthUser | null };
        });

        initializePostHog(phConfig, auth?.user);

        const root = createRoot(el);

        root.render(
            <StrictMode>
                <ErrorBoundary>
                    <Toaster position="top-right" richColors />
                    <App {...props} />
                </ErrorBoundary>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
