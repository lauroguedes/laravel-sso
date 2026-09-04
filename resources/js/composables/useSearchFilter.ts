import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';

/**
 * Keeps a search box in sync with a paginated Inertia index page.
 *
 * The visit replaces the history entry and preserves component state so that
 * typing does not stack up back-button steps or lose focus between requests.
 * Pass "only" to reload just the props the search actually changes.
 */
export function useSearchFilter(
    url: string,
    initial: string | null,
    only?: string[],
) {
    const search = ref(initial ?? '');

    watchDebounced(
        search,
        (value) => {
            router.get(
                url,
                { search: value || undefined },
                {
                    preserveState: true,
                    replace: true,
                    /*
                     * When the search only drives part of the page, ask for
                     * that part: a page whose other props are expensive should
                     * not rebuild them on every keystroke.
                     */
                    ...(only ? { only } : {}),
                },
            );
        },
        { debounce: 300 },
    );

    return { search };
}
