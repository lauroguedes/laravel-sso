<script setup lang="ts">
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/datetime';
import type {
    AuditEntry,
    DataTableColumn,
    DataTableSort,
} from '@/types/administration';

/**
 * The audit trail as a table.
 *
 * Shared between the whole-server view and an application's own, which differ
 * only in whether the application column is worth showing.
 *
 * Only time and event are sortable. Ordering by actor or address would turn
 * "what happened" into "what exists", which is a different question from the
 * one a trail answers, so those columns stay unsorted on purpose.
 */
const { showApplication = false, sort = null } = defineProps<{
    entries: AuditEntry[];
    showApplication?: boolean;
    sort?: DataTableSort;
}>();

defineEmits<{ 'update:sort': [DataTableSort] }>();

const columns = computed<DataTableColumn[]>(() => [
    { id: 'event', header: 'Event', sortable: true, alwaysVisible: true },
    { id: 'causer', header: 'Actor' },
    ...(showApplication ? [{ id: 'application', header: 'Application' }] : []),
    { id: 'ip_address', header: 'Address' },
    { id: 'created_at', header: 'When', sortable: true, align: 'right' },
]);
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="entries"
        :row-key="(entry) => entry.id"
        :sort="sort"
        empty="Nothing recorded yet."
        @update:sort="(next) => $emit('update:sort', next)"
    >
        <template #toolbar><slot name="toolbar" /></template>
        <template #filters><slot name="filters" /></template>
        <template #footer><slot name="footer" /></template>

        <template #cell-event="{ row }">
            <div class="flex items-center gap-2">
                <span class="font-medium">{{ row.label }}</span>
                <Badge
                    :variant="
                        row.stream === 'security' ? 'destructive' : 'secondary'
                    "
                >
                    {{ row.stream }}
                </Badge>
            </div>
            <code class="text-muted-foreground text-xs">{{ row.event }}</code>
        </template>

        <template #cell-causer="{ row }">
            <div v-if="row.causer" class="text-sm">
                <div>{{ row.causer.name }}</div>
                <div class="text-muted-foreground">{{ row.causer.email }}</div>
            </div>
            <span v-else class="text-muted-foreground text-sm">
                Not signed in
            </span>
        </template>

        <template #cell-application="{ row }">
            <span class="text-sm">{{ row.application ?? '—' }}</span>
        </template>

        <template #cell-ip_address="{ row }">
            <span class="text-muted-foreground text-sm">
                {{ row.ip_address ?? '—' }}
            </span>
        </template>

        <template #cell-created_at="{ row }">
            <span class="text-muted-foreground text-sm whitespace-nowrap">
                {{ formatDateTime(row.created_at) }}
            </span>
        </template>
    </DataTable>
</template>
