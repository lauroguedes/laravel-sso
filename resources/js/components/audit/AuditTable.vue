<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/datetime';
import type { AuditEntry } from '@/types/administration';

/**
 * The audit trail as a table.
 *
 * Shared between the whole-server view and an application's own, which differ
 * only in whether the application column is worth showing.
 */
defineProps<{
    entries: AuditEntry[];
    showApplication?: boolean;
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-lg border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Event</TableHead>
                    <TableHead>Actor</TableHead>
                    <TableHead v-if="showApplication">Application</TableHead>
                    <TableHead>Address</TableHead>
                    <TableHead class="text-right">When</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableEmpty
                    v-if="entries.length === 0"
                    :colspan="showApplication ? 5 : 4"
                >
                    Nothing recorded yet.
                </TableEmpty>

                <TableRow v-for="entry in entries" :key="entry.id">
                    <TableCell>
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ entry.label }}</span>
                            <Badge
                                :variant="
                                    entry.stream === 'security'
                                        ? 'destructive'
                                        : 'secondary'
                                "
                            >
                                {{ entry.stream }}
                            </Badge>
                        </div>
                        <code class="text-muted-foreground text-xs">
                            {{ entry.event }}
                        </code>
                    </TableCell>

                    <TableCell class="text-sm">
                        <template v-if="entry.causer">
                            <div>{{ entry.causer.name }}</div>
                            <div class="text-muted-foreground">
                                {{ entry.causer.email }}
                            </div>
                        </template>
                        <span v-else class="text-muted-foreground">
                            Not signed in
                        </span>
                    </TableCell>

                    <TableCell v-if="showApplication" class="text-sm">
                        {{ entry.application ?? '—' }}
                    </TableCell>

                    <TableCell class="text-muted-foreground text-sm">
                        {{ entry.ip_address ?? '—' }}
                    </TableCell>

                    <TableCell
                        class="text-muted-foreground text-right text-sm whitespace-nowrap"
                    >
                        {{ formatDateTime(entry.created_at) }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
