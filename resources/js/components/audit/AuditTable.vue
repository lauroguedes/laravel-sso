<script setup lang="ts">
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/datetime';
import type { DataTableColumn, AuditEntry } from '@/types/administration';

/**
 * The audit trail as a table.
 *
 * Shared between the whole-server view and an application's own, which differ
 * only in whether the application column is worth showing.
 *
 * Deliberately not sortable: the trail is a sequence, and reordering it by
 * actor or address would turn "what happened" into "what exists", losing the
 * one property that makes it readable as a history.
 */
const { showApplication = false } = defineProps<{
    entries: AuditEntry[];
    showApplication?: boolean;
}>();

const columns = computed<DataTableColumn[]>(() => [
    { id: 'event', header: 'Event', alwaysVisible: true },
    { id: 'causer', header: 'Actor' },
    ...(showApplication ? [{ id: 'application', header: 'Application' }] : []),
    { id: 'ip_address', header: 'Address' },
    { id: 'created_at', header: 'When', align: 'right' },
]);
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="entries"
        :row-key="(entry) => entry.id"
        empty="Nothing recorded yet."
    >
        <template #toolbar><slot name="toolbar" /></template>

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
