<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Ban, Eye, LogOut, MoreHorizontal, Pencil, Plus } from '@lucide/vue';
import DangerousAction from '@/components/DangerousAction.vue';
import FilterMenu from '@/components/FilterMenu.vue';
import type { FilterGroup } from '@/components/FilterMenu.vue';
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
import { formatDateTime } from '@/lib/datetime';
import { create, edit, index } from '@/routes/users';
import { destroy as revokeSessions } from '@/routes/users/sessions';
import { update as updateStatus } from '@/routes/users/status';
import type { DataTableColumn, UserSummary } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const { users, filters, availableRoles } = defineProps<{
    users: {
        data: UserSummary[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
    };
    filters: ListingFilters;
    availableRoles: string[];
    canManage: boolean;
}>();

const filterGroups = computed<FilterGroup[]>(() => [
    {
        key: 'status',
        label: 'Status',
        options: [
            { value: 'active', label: 'Active' },
            { value: 'disabled', label: 'Disabled' },
            { value: 'unverified', label: 'Unverified' },
        ],
    },
    {
        key: 'role',
        label: 'Platform role',
        options: availableRoles.map((role) => ({ value: role, label: role })),
    },
]);

/* Administrators may not disable themselves, so that row omits the action. */
const currentUserId = computed(() => usePage().props.auth.user.id);

const columns: DataTableColumn[] = [
    { id: 'status', header: 'Status', alwaysVisible: true },
    { id: 'name', header: 'Name', sortable: true, alwaysVisible: true },
    { id: 'roles', header: 'Roles' },
    { id: 'last_login_at', header: 'Last sign-in', sortable: true },
    { id: 'created_at', header: 'Created', sortable: true },
    { id: 'actions', header: '', align: 'right', alwaysVisible: true },
];

/** The user a confirmation is currently open for, and what it will do. */
const pending = ref<{
    user: UserSummary;
    action: 'signOut' | 'disable';
} | null>(null);

const confirmation = computed(() => {
    if (pending.value === null) {
        return null;
    }

    const { user, action } = pending.value;

    return action === 'signOut'
        ? {
              title: 'Sign this user out everywhere?',
              description: `${user.name} will be signed out of this server and every application that holds a token for them.`,
              label: 'Sign out everywhere',
          }
        : {
              title: 'Disable this user?',
              description: `${user.name} will be refused at sign-in, and every session and token they hold will be revoked.`,
              label: 'Disable user',
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

    const { user, action } = pending.value;

    if (action === 'signOut') {
        router.delete(revokeSessions(user.id).url, { preserveScroll: true });
    } else {
        router.put(
            updateStatus(user.id).url,
            { enabled: false },
            { preserveScroll: true },
        );
    }

    pending.value = null;
}

const { search, sort, applySort, setFilter } = useListingFilters(
    index().url,
    filters,
);
</script>

<template>
    <Head title="Users" />

    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Users"
                description="People who can authenticate through this Identity Provider"
            />

            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Add user
                </Link>
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :rows="users.data"
            :sort="sort"
            :row-key="(user) => user.id"
            empty="No users match this search."
            @update:sort="applySort"
        >
            <template #toolbar>
                <SearchInput
                    v-model="search"
                    placeholder="Search by name or email"
                    label="Search users"
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
                <Link :href="edit(row.id)" class="font-medium hover:underline">
                    {{ row.name }}
                </Link>
                <div class="text-muted-foreground text-sm">
                    {{ row.email }}
                </div>
            </template>

            <template #cell-roles="{ row }">
                <div class="flex flex-wrap gap-1">
                    <Badge
                        v-for="role in row.roles"
                        :key="role"
                        variant="secondary"
                    >
                        {{ role }}
                    </Badge>
                    <span
                        v-if="row.roles.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        —
                    </span>
                </div>
            </template>

            <template #cell-status="{ row }">
                <StatusIndicator
                    :active="!row.disabled"
                    :active-label="
                        row.email_verified ? 'Active' : 'Active, unverified'
                    "
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
                            <Link :href="edit(row.id)">
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
                                    pending = { user: row, action: 'signOut' }
                                "
                            >
                                <LogOut class="size-4" />
                                Sign out everywhere
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="!row.disabled && row.id !== currentUserId"
                                variant="destructive"
                                @select="
                                    pending = { user: row, action: 'disable' }
                                "
                            >
                                <Ban class="size-4" />
                                Disable user
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>

            <template #cell-last_login_at="{ row }">
                <span class="text-muted-foreground text-sm">
                    {{ formatDateTime(row.last_login_at) }}
                </span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-muted-foreground text-sm">
                    {{ formatDateTime(row.created_at) }}
                </span>
            </template>

            <template #footer>
                <Pagination
                    :links="users.links"
                    :from="users.from"
                    :to="users.to"
                    :total="users.total"
                    :per-page="users.per_page"
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
