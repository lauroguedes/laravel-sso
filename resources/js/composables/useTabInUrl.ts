/**
 * Keeps the open tab in the address bar.
 *
 * A settings page saves with a redirect, and a redirect that forgot which tab
 * it came from answers "saved" by throwing the reader back to the first one.
 * The server decides which tab opens; this keeps the URL in step as they
 * browse, so a reload, a bookmark and a redirect all land in the same place.
 *
 * Rewritten in place rather than visited: switching tab fetches nothing, and
 * asking the server for a page it already sent would make a free interaction
 * cost a round trip. Inertia compares paths, not queries, so its own record of
 * where the reader is stays correct.
 */
export function useTabInUrl(): {
    rememberTab: (value: string | number) => void;
} {
    function rememberTab(value: string | number) {
        const url = new URL(window.location.href);

        url.searchParams.set('tab', String(value));

        window.history.replaceState(window.history.state, '', url);
    }

    return { rememberTab };
}
