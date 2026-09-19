<script setup lang="ts">
import { UserPlus } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import SearchInput from '@/components/SearchInput.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    DataTableColumn,
    Paginator,
    UserCandidate,
} from '@/types/administration';

/**
 * Choosing a person to add to an application.
 *
 * The same shape serves granting access and assigning a manager: search, the
 * people the server says are eligible, a page at a time, and one control each.
 * The server does the narrowing, so what is offered here is always something
 * the request will accept.
 */
defineProps<{
    title: string;
    description: string;
    /** Names the search box for a screen reader. */
    searchLabel: string;
    action: string;
    candidates: Paginator<UserCandidate>;
    empty: string;
}>();

const search = defineModel<string>('search', { required: true });

const emit = defineEmits<{
    select: [number];
    'update:page': [number];
    'update:perPage': [number];
}>();

const columns: DataTableColumn[] = [
    { id: 'user', header: 'User', alwaysVisible: true },
    { id: 'actions', header: '', align: 'right', alwaysVisible: true },
];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription>{{ description }}</CardDescription>
        </CardHeader>

        <CardContent>
            <DataTable
                :columns="columns"
                :rows="candidates.data"
                :row-key="(candidate) => candidate.id"
                :empty="empty"
            >
                <template #toolbar>
                    <SearchInput
                        v-model="search"
                        placeholder="Search by name or email"
                        :label="searchLabel"
                    />
                </template>

                <template #cell-user="{ row }">
                    <div class="font-medium">{{ row.name }}</div>
                    <div class="text-muted-foreground truncate text-sm">
                        {{ row.email }}
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="emit('select', row.id)"
                    >
                        <UserPlus class="size-4" />
                        {{ action }}
                    </Button>
                </template>

                <template #footer>
                    <Pagination
                        :paginator="candidates"
                        @update:page="(page) => emit('update:page', page)"
                        @update:per-page="
                            (size) => emit('update:perPage', size)
                        "
                    />
                </template>
            </DataTable>
        </CardContent>
    </Card>
</template>
