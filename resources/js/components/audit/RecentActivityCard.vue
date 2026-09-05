<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/datetime';
import type { AuditEntry } from '@/types/administration';

/**
 * The last few entries from one audit stream, as shown on the dashboard.
 */
defineProps<{
    title: string;
    entries: AuditEntry[];
    causerFallback: string;
    showAddress?: boolean;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
        </CardHeader>

        <CardContent>
            <ul v-if="entries.length > 0" class="divide-y">
                <li
                    v-for="entry in entries"
                    :key="entry.id"
                    class="flex items-baseline justify-between gap-3 py-2 text-sm first:pt-0"
                >
                    <div class="min-w-0">
                        <div>{{ entry.label }}</div>
                        <div class="text-muted-foreground truncate">
                            {{ entry.causer?.name ?? causerFallback }}
                            <template v-if="showAddress && entry.ip_address">
                                · {{ entry.ip_address }}
                            </template>
                        </div>
                    </div>
                    <span class="text-muted-foreground shrink-0 text-xs">
                        {{ formatDateTime(entry.created_at) }}
                    </span>
                </li>
            </ul>

            <p v-else class="text-muted-foreground text-sm">
                Nothing recorded yet.
            </p>
        </CardContent>
    </Card>
</template>
