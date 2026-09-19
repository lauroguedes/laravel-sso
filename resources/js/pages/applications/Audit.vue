<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ApplicationLayout from '@/layouts/applications/Layout.vue';
import AuditTable from '@/components/audit/AuditTable.vue';
import Pagination from '@/components/Pagination.vue';
import SearchInput from '@/components/SearchInput.vue';
import { useListingFilters } from '@/composables/useListingFilters';
import { index } from '@/routes/applications';
import { audit } from '@/routes/applications';
import type {
    ApplicationHeader,
    ApplicationSection,
    AuditEntry,
    Paginator,
} from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { application, filters } = defineProps<{
    application: ApplicationHeader;
    sections: ApplicationSection[];
    canManageApplication: boolean;
    filters: { search: string | null };
    entries: Paginator<AuditEntry>;
}>();

const { search, sort, applySort, setFilter, goToPage } = useListingFilters(
    audit(application.id).url,
    filters,
);
</script>

<template>
    <Head :title="`${application.name} audit`" />

    <ApplicationLayout
        :application="application"
        :sections="sections"
        description="What has been done to this application, and by whom"
        :can-manage="canManageApplication"
    >
        <AuditTable
            :entries="entries.data"
            :sort="sort"
            @update:sort="applySort"
        >
            <template #toolbar>
                <SearchInput
                    v-model="search"
                    placeholder="Search by event or address"
                    label="Search this application's audit trail"
                />
            </template>

            <template #footer>
                <Pagination
                    :paginator="entries"
                    @update:page="goToPage"
                    @update:per-page="(size) => setFilter('per_page', size)"
                />
            </template>
        </AuditTable>
    </ApplicationLayout>
</template>
