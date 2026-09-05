<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AuditTable from '@/components/audit/AuditTable.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationLink } from '@/components/Pagination.vue';
import SearchInput from '@/components/SearchInput.vue';
import { Button } from '@/components/ui/button';
import { useSearchFilter } from '@/composables/useSearchFilter';
import { index } from '@/routes/audit';
import type { AuditEntry } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Audit', href: index() }],
    },
});

const { filters } = defineProps<{
    filters: { search: string | null; stream: string | null };
    streams: { value: string; label: string }[];
    entries: {
        data: AuditEntry[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
    };
}>();

const { search } = useSearchFilter(index().url, filters.search);

function showStream(stream: string | null) {
    router.get(
        index().url,
        { stream: stream ?? undefined, search: filters.search ?? undefined },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Audit" />

    <div class="px-4 py-6">
        <Heading
            title="Audit"
            description="What has been done on this server, and by whom"
        />

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <SearchInput
                v-model="search"
                placeholder="Search by event, description or address"
                label="Search the audit trail"
            />

            <div class="flex flex-wrap gap-1">
                <Button
                    :variant="filters.stream === null ? 'secondary' : 'ghost'"
                    size="sm"
                    @click="showStream(null)"
                >
                    All
                </Button>
                <Button
                    v-for="stream in streams"
                    :key="stream.value"
                    :variant="
                        filters.stream === stream.value ? 'secondary' : 'ghost'
                    "
                    size="sm"
                    @click="showStream(stream.value)"
                >
                    {{ stream.label }}
                </Button>
            </div>
        </div>

        <AuditTable :entries="entries.data" show-application />

        <Pagination
            :links="entries.links"
            :from="entries.from"
            :to="entries.to"
        />
    </div>
</template>
