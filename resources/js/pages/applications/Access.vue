<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { UserPlus, X } from '@lucide/vue';
import ApplicationLayout from '@/layouts/applications/Layout.vue';
import DangerousAction from '@/components/DangerousAction.vue';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationLink } from '@/components/Pagination.vue';
import SearchInput from '@/components/SearchInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useListingFilters } from '@/composables/useListingFilters';
import { index } from '@/routes/applications';
import {
    destroy as revokeGrant,
    index as grantsIndex,
    store as storeGrant,
    update as updateGrant,
} from '@/routes/applications/grants';
import type {
    DataTableColumn,
    ApplicationGrantSummary,
    ApplicationHeader,
    ApplicationRoleOption,
    UserCandidate,
} from '@/types/administration';

const grantColumns: DataTableColumn[] = [
    { id: 'user', header: 'User', alwaysVisible: true },
    { id: 'role', header: 'Role' },
    { id: 'actions', header: '', align: 'right', alwaysVisible: true },
];

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { application, filters, roles } = defineProps<{
    application: ApplicationHeader;
    canManageApplication: boolean;
    filters: { search: string | null; granted: string | null };
    grants: {
        data: ApplicationGrantSummary[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    candidates: UserCandidate[];
    roles: ApplicationRoleOption[];
    canManage: boolean;
}>();

const { search, setFilter } = useListingFilters(
    grantsIndex(application.id).url,
    filters,
    /*
     * Both lists, because this page has two search boxes: one narrows the
     * candidates to add, the other the people who already have access. Asking
     * for only the candidates left the grants table showing a result the URL
     * said it had filtered.
     */
    ['candidates', 'grants', 'filters'],
);

/*
 * A second, separately debounced box: this one narrows the list of people who
 * already have access, while "search" above narrows the candidates to add.
 */
const granted = ref(filters.granted ?? '');

watchDebounced(granted, (value) => setFilter('granted', value || null), {
    debounce: 300,
});

/** Sentinel for "no role", since a select cannot carry a null value. */
const NO_ROLE = 'none';

function grantAccess(userId: number) {
    router.post(storeGrant(application.id).url, {
        user_id: userId,
        application_role_id: null,
    });
}

function changeRole(grant: ApplicationGrantSummary, value: string) {
    router.put(updateGrant([application.id, grant.id]).url, {
        application_role_id: value === NO_ROLE ? null : Number(value),
    });
}

/**
 * The role a grant holds, resolved against the roles already on the page
 * rather than repeated on every row of the payload.
 */
function roleName(grant: ApplicationGrantSummary): string {
    return roles.find((role) => role.id === grant.role_id)?.name ?? 'No role';
}

function revoke(grant: ApplicationGrantSummary) {
    router.delete(revokeGrant([application.id, grant.id]).url);
}
</script>

<template>
    <Head :title="`${application.name} access`" />

    <ApplicationLayout
        :application="application"
        description="Who may sign in to this application, and the role they hold there"
        :can-manage="canManageApplication"
    >
        <Card>
            <CardHeader>
                <CardTitle>Users with access</CardTitle>
                <CardDescription>
                    A user with access but no role can sign in without being
                    granted any capability.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <DataTable
                    :columns="grantColumns"
                    :rows="grants.data"
                    :row-key="(grant) => grant.id"
                    empty="Nobody has been granted access yet."
                >
                    <template #toolbar>
                        <SearchInput
                            v-model="granted"
                            placeholder="Search by name or email"
                            label="Search users with access"
                        />
                    </template>

                    <template #cell-user="{ row }">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ row.user.name }}</span>
                            <Badge
                                v-if="row.user.disabled"
                                variant="destructive"
                            >
                                Disabled
                            </Badge>
                        </div>
                        <div class="text-muted-foreground text-sm">
                            {{ row.user.email }}
                        </div>
                    </template>

                    <template #cell-role="{ row }">
                        <Select
                            v-if="canManage"
                            :model-value="
                                row.role_id === null
                                    ? NO_ROLE
                                    : String(row.role_id)
                            "
                            @update:model-value="
                                (value) => changeRole(row, String(value))
                            "
                        >
                            <SelectTrigger
                                class="w-44"
                                :aria-label="`Role for ${row.user.name}`"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NO_ROLE"
                                    >No role</SelectItem
                                >
                                <SelectItem
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="String(role.id)"
                                >
                                    {{ role.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <span v-else class="text-sm">{{ roleName(row) }}</span>
                    </template>

                    <template #cell-actions="{ row }">
                        <DangerousAction
                            v-if="canManage"
                            title="Revoke access?"
                            :description="`${row.user.name} will no longer be able to sign in to ${application.name}.`"
                            confirm-label="Revoke access"
                            @confirm="revoke(row)"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Revoke access for ${row.user.name}`"
                            >
                                <X class="size-4" />
                            </Button>
                        </DangerousAction>
                    </template>

                    <template #footer>
                        <Pagination
                            :links="grants.links"
                            :from="grants.from"
                            :to="grants.to"
                            :total="grants.total"
                        />
                    </template>
                </DataTable>
            </CardContent>
        </Card>

        <Card v-if="canManage">
            <CardHeader>
                <CardTitle>Grant access</CardTitle>
                <CardDescription>
                    Search for a user who does not yet have access. Assign a
                    role once they have been added.
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-4">
                <SearchInput
                    v-model="search"
                    placeholder="Search by name or email"
                    label="Search users to grant access"
                />

                <ul
                    v-if="candidates.length > 0"
                    class="divide-y rounded-lg border"
                >
                    <li
                        v-for="candidate in candidates"
                        :key="candidate.id"
                        class="flex items-center justify-between gap-3 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <div class="font-medium">
                                {{ candidate.name }}
                            </div>
                            <div class="text-muted-foreground truncate text-sm">
                                {{ candidate.email }}
                            </div>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            @click="grantAccess(candidate.id)"
                        >
                            <UserPlus class="size-4" />
                            Grant
                        </Button>
                    </li>
                </ul>

                <p v-else class="text-muted-foreground text-sm">
                    No users match this search, or everyone matching already has
                    access.
                </p>
            </CardContent>
        </Card>
    </ApplicationLayout>
</template>
