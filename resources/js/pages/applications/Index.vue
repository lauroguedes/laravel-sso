<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Ban, Eye, KeyRound, MoreHorizontal, Pencil, Plus } from '@lucide/vue';
import DangerousAction from '@/components/DangerousAction.vue';
import DataTable from '@/components/DataTable.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationLink } from '@/components/Pagination.vue';
import SearchInput from '@/components/SearchInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useListingFilters } from '@/composables/useListingFilters';
import type { ListingFilters } from '@/composables/useListingFilters';
import { create, edit, index, show } from '@/routes/applications';
import { update as updateStatus } from '@/routes/applications/status';
import { destroy as revokeTokens } from '@/routes/applications/tokens';
import type {
    DataTableColumn,
    ApplicationSummary,
} from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { applications, filters } = defineProps<{
    applications: {
        data: ApplicationSummary[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: ListingFilters;
    canManage: boolean;
}>();

const columns: DataTableColumn[] = [
    { id: 'revoked', header: 'Status', sortable: true, alwaysVisible: true },
    { id: 'name', header: 'Name', sortable: true, alwaysVisible: true },
    { id: 'type_label', header: 'Type' },
    { id: 'id', header: 'Client ID' },
    { id: 'actions', header: '', align: 'right', alwaysVisible: true },
];

/** The application a confirmation is currently open for, and what it will do. */
const pending = ref<{
    application: ApplicationSummary;
    action: 'revokeTokens' | 'disable';
} | null>(null);

const confirmation = computed(() => {
    if (pending.value === null) {
        return null;
    }

    const { application, action } = pending.value;

    return action === 'revokeTokens'
        ? {
              title: 'Revoke every token this application holds?',
              description: `${application.name} will lose access immediately and everyone using it will be sent back to sign in. The application itself keeps working.`,
              label: 'Revoke tokens',
          }
        : {
              title: 'Disable this application?',
              description: `${application.name} will stop working immediately and every token it holds will be revoked.`,
              label: 'Disable application',
          };
});

/*
 * The dialog closes itself when its action is clicked, and that close arrives
 * in the same tick as the confirm. Clearing on the next microtask instead lets
 * the handler below still see what it is confirming.
 */
function dismiss() {
    queueMicrotask(() => {
        pending.value = null;
    });
}

function confirm() {
    if (pending.value === null) {
        return;
    }

    const { application, action } = pending.value;

    if (action === 'revokeTokens') {
        router.delete(revokeTokens(application.id).url, {
            preserveScroll: true,
        });
    } else {
        router.put(
            updateStatus(application.id).url,
            { enabled: false },
            { preserveScroll: true },
        );
    }

    pending.value = null;
}

const { search, sort, applySort } = useListingFilters(index().url, filters);
</script>

<template>
    <Head title="Applications" />

    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Applications"
                description="OAuth2 and OpenID Connect clients that authenticate through this server"
            />

            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Add application
                </Link>
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :rows="applications.data"
            :sort="sort"
            :row-key="(application) => application.id"
            empty="No applications match this search."
            @update:sort="applySort"
        >
            <template #toolbar>
                <SearchInput
                    v-model="search"
                    placeholder="Search by name or client ID"
                    label="Search applications"
                />
            </template>

            <template #cell-name="{ row }">
                <Link :href="show(row.id)" class="font-medium hover:underline">
                    {{ row.name }}
                </Link>
                <div
                    v-if="row.description"
                    class="text-muted-foreground line-clamp-1 text-sm"
                >
                    {{ row.description }}
                </div>
            </template>

            <template #cell-type_label="{ row }">
                <span class="text-sm">{{ row.type_label }}</span>
            </template>

            <template #cell-id="{ row }">
                <!--
                    Truncated: a client id is a UUID and pushes the table into
                    a horizontal scroll it does not otherwise need. The whole
                    value, with a copy button, is on the application's page.
                -->
                <code
                    class="text-muted-foreground block max-w-[18ch] truncate text-xs"
                    :title="row.id"
                >
                    {{ row.id }}
                </code>
            </template>

            <template #cell-revoked="{ row }">
                <StatusIndicator
                    :active="row.enabled"
                    active-label="Enabled"
                    inactive-label="Disabled"
                />
            </template>

            <template #cell-actions="{ row }">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Actions for ${row.name}`"
                        >
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end">
                        <DropdownMenuItem as-child>
                            <Link :href="show(row.id)">
                                <Eye class="size-4" />
                                View
                            </Link>
                        </DropdownMenuItem>

                        <DropdownMenuItem v-if="canManage" as-child>
                            <Link :href="edit(row.id)">
                                <Pencil class="size-4" />
                                Edit
                            </Link>
                        </DropdownMenuItem>

                        <template v-if="canManage">
                            <DropdownMenuSeparator />

                            <DropdownMenuItem
                                @select="
                                    pending = {
                                        application: row,
                                        action: 'revokeTokens',
                                    }
                                "
                            >
                                <KeyRound class="size-4" />
                                Revoke issued tokens
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="row.enabled"
                                variant="destructive"
                                @select="
                                    pending = {
                                        application: row,
                                        action: 'disable',
                                    }
                                "
                            >
                                <Ban class="size-4" />
                                Disable application
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </DataTable>

        <DangerousAction
            v-if="confirmation"
            :open="pending !== null"
            :title="confirmation.title"
            :description="confirmation.description"
            :confirm-label="confirmation.label"
            @update:open="(value) => !value && dismiss()"
            @confirm="confirm"
        />

        <Pagination
            :links="applications.links"
            :from="applications.from"
            :to="applications.to"
            :total="applications.total"
        />
    </div>
</template>
