import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';

/**
 * Keeps a search box in sync with a paginated Inertia index page.
 *
 * The visit replaces the history entry and preserves component state so that
 * typing does not stack up back-button steps or lose focus between requests.
 */
export function useSearchFilter(url: string, initial: string | null) {
    const search = ref(initial ?? '');

    watchDebounced(
        search,
        (value) => {
            router.get(
                url,
                { search: value || undefined },
                { preserveState: true, replace: true },
            );
        },
        { debounce: 300 },
    );

    return { search };
}
