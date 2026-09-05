<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ApplicationNav from '@/components/applications/ApplicationNav.vue';
import AuditTable from '@/components/audit/AuditTable.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationLink } from '@/components/Pagination.vue';
import SearchInput from '@/components/SearchInput.vue';
import { useSearchFilter } from '@/composables/useSearchFilter';
import { index } from '@/routes/applications';
import { audit } from '@/routes/applications';
import type { AuditEntry } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { application, filters } = defineProps<{
    application: { id: string; name: string };
    filters: { search: string | null };
    entries: {
        data: AuditEntry[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
    };
}>();

const { search } = useSearchFilter(audit(application.id).url, filters.search);
</script>

<template>
    <Head :title="`${application.name} audit`" />

    <div class="max-w-4xl px-4 py-6">
        <Heading
            :title="application.name"
            description="What has been done to this application"
        />

        <ApplicationNav :application-id="application.id" />

        <SearchInput
            v-model="search"
            class="mb-4"
            placeholder="Search by event or address"
            label="Search this application's audit trail"
        />

        <AuditTable :entries="entries.data" />

        <Pagination
            :links="entries.links"
            :from="entries.from"
            :to="entries.to"
        />
    </div>
</template>
