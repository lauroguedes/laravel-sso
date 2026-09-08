/**
 * Date formatting shared by the administration pages.
 *
 * The interface shows absolute local times rather than "3 minutes ago": an
 * operator reading an audit trail is usually correlating it with something
 * else, and a relative time cannot be compared against another system's logs.
 *
 * One format everywhere — d/m/Y H:i:s — rather than the browser's locale
 * default, so that a timestamp copied out of one screen reads the same as one
 * copied out of another, and day and month cannot be mistaken for each other
 * between readers in different places.
 */
function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function format(date: Date): string {
    const day = `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
    const time = `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;

    return `${day} ${time}`;
}

export function formatDateTime(value: string | null, empty = '—'): string {
    return value ? format(new Date(value)) : empty;
}

/** Format a unix timestamp, as the sessions table stores. */
export function formatTimestamp(seconds: number | null): string {
    return seconds ? format(new Date(seconds * 1000)) : '—';
}
