/**
 * CSRF headers for same-origin fetch POSTs.
 *
 * Prefer the XSRF-TOKEN cookie (kept in sync by Laravel on every response).
 * The Blade <meta name="csrf-token"> goes stale after Inertia login / session
 * regeneration and must not be sent alongside a valid XSRF cookie - Laravel
 * checks X-CSRF-TOKEN before X-XSRF-TOKEN.
 */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export function metaCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || ''
    );
}

export function csrfHeaders(
    extra: Record<string, string> = {},
): Record<string, string> {
    const headers: Record<string, string> = {
        'X-Requested-With': 'XMLHttpRequest',
        ...extra,
    };

    const xsrf = xsrfToken();
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
        return headers;
    }

    const csrf = metaCsrfToken();
    if (csrf) {
        headers['X-CSRF-TOKEN'] = csrf;
    }

    return headers;
}
