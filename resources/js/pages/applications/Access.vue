<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import ApplicationLayout from '@/layouts/applications/Layout.vue';
import CandidatePicker from '@/components/applications/CandidatePicker.vue';
import DangerousAction from '@/components/DangerousAction.vue';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
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
import type { ListingFilters } from '@/composables/useListingFilters';
import { index } from '@/routes/applications';
import {
    destroy as revokeGrant,
    index as grantsIndex,
    store as storeGrant,
    update as updateGrant,
} from '@/routes/applications/grants';
import type {
    ApplicationGrantSummary,
    ApplicationHeader,
    ApplicationRoleOption,
    ApplicationSection,
    DataTableColumn,
    Paginator,
    UserCandidate,
} from '@/types/administration';

const grantColumns: DataTableColumn[] = [
    { id: 'user', header: 'User', alwaysVisible: true },
    { id: 'role', header: 'Role', alwaysVisible: true },
    { id: 'actions', header: '', align: 'right', alwaysVisible: true },
];

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { application, grantFilters, candidateFilters, roles } = defineProps<{
    application: ApplicationHeader;
    sections: ApplicationSection[];
    canManageApplication: boolean;
    grants: Paginator<ApplicationGrantSummary>;
    grantFilters: ListingFilters;
    candidates: Paginator<UserCandidate>;
    candidateFilters: ListingFilters;
    roles: ApplicationRoleOption[];
    canManage: boolean;
}>();

/*
 * Two listings, so each keeps its parameters under its own prefix and reloads
 * only its own props: one narrows the people who already have access, the
 * other the candidates to add.
 */
const {
    search: grantSearch,
    setFilter: filterGrants,
    goToPage: goToGrantsPage,
} = useListingFilters(
    grantsIndex(application.id).url,
    grantFilters,
    ['grants', 'grantFilters'],
    'grants_',
);

const {
    search: candidateSearch,
    setFilter: filterCandidates,
    goToPage: goToCandidatesPage,
} = useListingFilters(
    grantsIndex(application.id).url,
    candidateFilters,
    ['candidates', 'candidateFilters'],
    'candidates_',
);

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
        :sections="sections"
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
                    :empty="
                        grantFilters.search
                            ? 'Nobody with access matches this search.'
                            : 'Nobody has been granted access yet.'
                    "
                >
                    <template #toolbar>
                        <SearchInput
                            v-model="grantSearch"
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
                            :paginator="grants"
                            @update:page="goToGrantsPage"
                            @update:per-page="
                                (size) => filterGrants('per_page', size)
                            "
                        />
                    </template>
                </DataTable>
            </CardContent>
        </Card>

        <CandidatePicker
            v-if="canManage"
            v-model:search="candidateSearch"
            title="Grant access"
            description="Search for a user who does not yet have access. Assign a role once they have been added."
            search-label="Search users to grant access"
            action="Grant"
            :candidates="candidates"
            empty="No users match this search, or everyone matching already has access."
            @select="grantAccess"
            @update:page="goToCandidatesPage"
            @update:per-page="(size) => filterCandidates('per_page', size)"
        />
    </ApplicationLayout>
</template>
