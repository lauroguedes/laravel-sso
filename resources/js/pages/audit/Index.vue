<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuditTable from '@/components/audit/AuditTable.vue';
import FilterMenu from '@/components/FilterMenu.vue';
import type { FilterGroup } from '@/components/FilterMenu.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import SearchInput from '@/components/SearchInput.vue';
import { useListingFilters } from '@/composables/useListingFilters';
import { index } from '@/routes/audit';
import type { AuditEntry } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Audit', href: index() }],
    },
});

const { filters, streams } = defineProps<{
    filters: { search: string | null; stream: string | null };
    streams: { value: string; label: string }[];
    entries: {
        data: AuditEntry[];
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
        per_page: number;
    };
}>();

const { search, sort, applySort, setFilter } = useListingFilters(
    index().url,
    filters,
);

const filterGroups = computed<FilterGroup[]>(() => [
    { key: 'stream', label: 'Stream', options: streams },
]);
</script>

<template>
    <Head title="Audit" />

    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-8">
        <Heading
            title="Audit"
            description="What has been done on this server, and by whom"
        />

        <AuditTable
            :entries="entries.data"
            :sort="sort"
            show-application
            @update:sort="applySort"
        >
            <template #toolbar>
                <SearchInput
                    v-model="search"
                    placeholder="Search by event, description or address"
                    label="Search the audit trail"
                />
            </template>

            <template #filters>
                <FilterMenu
                    :groups="filterGroups"
                    :active="filters"
                    @change="setFilter"
                />
            </template>

            <template #footer>
                <Pagination
                    :from="entries.from"
                    :to="entries.to"
                    :prev-page-url="entries.prev_page_url"
                    :next-page-url="entries.next_page_url"
                    :per-page="entries.per_page"
                    @update:per-page="(size) => setFilter('per_page', size)"
                />
            </template>
        </AuditTable>
    </div>
</template>
