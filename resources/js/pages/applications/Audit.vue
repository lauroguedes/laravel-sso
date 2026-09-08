<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ApplicationLayout from '@/layouts/applications/Layout.vue';
import AuditTable from '@/components/audit/AuditTable.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationLink } from '@/components/Pagination.vue';
import SearchInput from '@/components/SearchInput.vue';
import { useListingFilters } from '@/composables/useListingFilters';
import { index } from '@/routes/applications';
import { audit } from '@/routes/applications';
import type { ApplicationHeader, AuditEntry } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { application, filters } = defineProps<{
    application: ApplicationHeader;
    canManageApplication: boolean;
    filters: { search: string | null };
    entries: {
        data: AuditEntry[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
    };
}>();

const { search } = useListingFilters(audit(application.id).url, filters);
</script>

<template>
    <Head :title="`${application.name} audit`" />

    <ApplicationLayout
        :application="application"
        description="What has been done to this application, and by whom"
        :can-manage="canManageApplication"
    >
        <AuditTable :entries="entries.data">
            <template #toolbar>
                <SearchInput
                    v-model="search"
                    placeholder="Search by event or address"
                    label="Search this application's audit trail"
                />
            </template>
        </AuditTable>

        <Pagination
            :links="entries.links"
            :from="entries.from"
            :to="entries.to"
        />
    </ApplicationLayout>
</template>
