/**
 * Debug utility for conditional logging
 * Only logs in development mode
 */

const isDev = import.meta.env.DEV;

export const debug = {
    log: (...args: any[]) => {
        if (isDev) {
            console.log(...args);
        }
    },
    warn: (...args: any[]) => {
        if (isDev) {
            console.warn(...args);
        }
    },
    error: (...args: any[]) => {
        // Always log errors, even in production
        console.error(...args);
    },
    info: (...args: any[]) => {
        if (isDev) {
            console.info(...args);
        }
    },
};
