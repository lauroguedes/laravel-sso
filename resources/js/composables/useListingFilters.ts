import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { reactive, ref } from 'vue';
import type { DataTableSort } from '@/types/administration';

export type ListingFilters = {
    search: string | null;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
    per_page?: number | null;
    /** Anything else the listing narrows by, such as the audit stream. */
    [key: string]: string | number | null | undefined;
};

/**
 * Keeps every filter of a paginated index page in sync with its URL.
 *
 * One owner for the whole query string, deliberately. Search, sort and any
 * page-specific filter were previously written by separate visits, each
 * sending only its own part — so typing in the search box discarded the active
 * sort, and choosing an audit stream discarded the search.
 *
 * Whatever else the server put in "filters" is carried along untouched, which
 * is what makes that class of bug impossible rather than merely fixed.
 *
 * The visit replaces the history entry and preserves component state, so that
 * typing neither stacks up back-button steps nor loses focus between requests.
 */
export function useListingFilters(
    url: string,
    initial: ListingFilters,
    only?: string[],
) {
    const search = ref(initial.search ?? '');

    const sort = ref<DataTableSort>(
        initial.sort == null || initial.direction == null
            ? null
            : { column: initial.sort, direction: initial.direction },
    );

    /* Every filter this page has beyond the three handled above. */
    const extra = reactive<Record<string, string | number | undefined>>(
        Object.fromEntries(
            Object.entries(initial)
                .filter(
                    ([key]) => !['search', 'sort', 'direction'].includes(key),
                )
                .map(([key, value]) => [key, value ?? undefined]),
        ),
    );

    function visit() {
        router.get(
            url,
            {
                ...extra,
                search: search.value || undefined,
                sort: sort.value?.column,
                direction: sort.value?.direction,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                /*
                 * When the filters only drive part of the page, ask for that
                 * part: a page whose other props are expensive should not
                 * rebuild them on every keystroke.
                 */
                ...(only ? { only } : {}),
            },
        );
    }

    watchDebounced(search, visit, { debounce: 300 });

    /**
     * Order by a column, in the database rather than in the browser: these
     * listings are paginated, and sorting the page on screen would reorder
     * fifteen rows while appearing to have ordered the whole table.
     */
    function applySort(next: DataTableSort) {
        sort.value = next;

        visit();
    }

    /**
     * Narrow by one of the page's own filters, keeping the rest.
     */
    function setFilter(key: string, value: string | number | null) {
        extra[key] = value ?? undefined;

        /* A narrower list starts again from its first page. */
        if (key !== 'page') {
            extra.page = undefined;
        }

        visit();
    }

    return { search, sort, applySort, setFilter, filters: extra };
}
