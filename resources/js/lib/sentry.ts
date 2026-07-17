import * as Sentry from '@sentry/react';

const dsn = import.meta.env.VITE_SENTRY_DSN as string | undefined;

export function initializeSentry(): void {
    if (!dsn || import.meta.env.DEV) {
        return;
    }

    Sentry.init({
        dsn,
        environment: import.meta.env.MODE,
        tracesSampleRate: 0.1,
    });
}

export { Sentry };
