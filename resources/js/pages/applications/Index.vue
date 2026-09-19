<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Ban, Eye, KeyRound, MoreHorizontal, Pencil, Plus } from '@lucide/vue';
import ApplicationTypeBadge from '@/components/applications/ApplicationTypeBadge.vue';
import CopyButton from '@/components/CopyButton.vue';
import DangerousAction from '@/components/DangerousAction.vue';
import FilterMenu from '@/components/FilterMenu.vue';
import type { FilterGroup } from '@/components/FilterMenu.vue';
import DataTable from '@/components/DataTable.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
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
    ApplicationListRow,
    DataTableColumn,
    Paginator,
} from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { applications, filters, applicationTypes } = defineProps<{
    applications: Paginator<ApplicationListRow>;
    filters: ListingFilters;
    applicationTypes: { value: string; label: string }[];
    canAdminister: boolean;
}>();

const filterGroups = computed<FilterGroup[]>(() => [
    {
        key: 'status',
        label: 'Status',
        options: [
            { value: 'enabled', label: 'Enabled' },
            { value: 'disabled', label: 'Disabled' },
        ],
    },
    { key: 'type', label: 'Type', options: applicationTypes },
]);

const columns: DataTableColumn[] = [
    { id: 'revoked', header: 'Status', sortable: true, alwaysVisible: true },
    { id: 'name', header: 'Name', sortable: true, alwaysVisible: true },
    { id: 'type_label', header: 'Type' },
    { id: 'id', header: 'Client ID' },
    { id: 'actions', header: '', align: 'right', alwaysVisible: true },
];

/** The application a confirmation is currently open for, and what it will do. */
const pending = ref<{
    application: ApplicationListRow;
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

const { search, sort, applySort, setFilter, goToPage } = useListingFilters(
    index().url,
    filters,
);
</script>

<template>
    <Head title="Applications" />

    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Applications"
                description="OAuth2 and OpenID Connect clients that authenticate through this server"
            />

            <Button v-if="canAdminister" as-child>
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

            <template #filters>
                <FilterMenu
                    :groups="filterGroups"
                    :active="filters"
                    @change="setFilter"
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
                <ApplicationTypeBadge
                    :type="row.type"
                    :label="row.type_label"
                    :description="row.type_description"
                />
            </template>

            <template #cell-id="{ row }">
                <!--
                    Truncated, because a client id is a UUID and would push the
                    table into a horizontal scroll. The copy button beside it
                    is what the value is actually wanted for.
                -->
                <div class="flex items-center gap-1">
                    <code
                        class="text-muted-foreground block max-w-[16ch] truncate text-xs"
                        :title="row.id"
                    >
                        {{ row.id }}
                    </code>
                    <CopyButton :value="row.id" label="Copy client ID" />
                </div>
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

                        <DropdownMenuItem v-if="row.can_manage" as-child>
                            <Link :href="edit(row.id)">
                                <Pencil class="size-4" />
                                Edit
                            </Link>
                        </DropdownMenuItem>

                        <template v-if="canAdminister">
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

            <template #footer>
                <Pagination
                    :paginator="applications"
                    @update:page="goToPage"
                    @update:per-page="(size) => setFilter('per_page', size)"
                />
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
    </div>
</template>
