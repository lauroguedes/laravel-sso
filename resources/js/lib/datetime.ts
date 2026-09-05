/**
 * Date formatting shared by the administration pages.
 *
 * The interface shows absolute local times rather than "3 minutes ago": an
 * operator reading an audit trail is usually correlating it with something
 * else, and a relative time cannot be compared against another system's logs.
 */
export function formatDateTime(value: string | null, empty = '—'): string {
    return value ? new Date(value).toLocaleString() : empty;
}

export function formatDate(value: string | null, empty = '—'): string {
    return value ? new Date(value).toLocaleDateString() : empty;
}

/** Format a unix timestamp, as the sessions table stores. */
export function formatTimestamp(seconds: number | null): string {
    return seconds ? new Date(seconds * 1000).toLocaleString() : '—';
}
