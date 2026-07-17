/**
 * Resolve a storage path to an authenticated media URL.
 * Never use /storage/ for user uploads in production.
 */
export function mediaUrl(path: string | null | undefined): string | undefined {
    if (!path) {
        return undefined;
    }

    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    if (path.startsWith('/media/')) {
        return path;
    }

    if (path.startsWith('/storage/')) {
        return path.replace(/^\/storage\//, '/media/');
    }

    return `/media/${path.replace(/^\/+/, '')}`;
}
