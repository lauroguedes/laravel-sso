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
import { useListingFilters } from '@/composables/useListingFilters';
import type { ListingFilters } from '@/composables/useListingFilters';
import { index } from '@/routes/applications';
import {
    destroy as removeManager,
    index as managersIndex,
    store as addManager,
} from '@/routes/applications/managers';
import type {
    ApplicationHeader,
    ApplicationSection,
    DataTableColumn,
    Paginator,
    UserCandidate,
} from '@/types/administration';

/**
 * Who looks after this application.
 *
 * Separate from Access next door: that decides who may sign in, this decides
 * who may change what the application is. Somebody maintains an application
 * they never sign in to, and plenty of people sign in to one they must never
 * reconfigure.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

type Manager = {
    id: number;
    name: string;
    email: string;
    disabled: boolean;
};

const { application, managerFilters, candidateFilters } = defineProps<{
    application: ApplicationHeader;
    sections: ApplicationSection[];
    canManageApplication: boolean;
    managers: Paginator<Manager>;
    managerFilters: ListingFilters;
    candidates: Paginator<UserCandidate>;
    candidateFilters: ListingFilters;
}>();

/* Two listings, each with its own prefix, as the access page has. */
const {
    search: managerSearch,
    setFilter: filterManagers,
    goToPage: goToManagersPage,
} = useListingFilters(
    managersIndex(application.id).url,
    managerFilters,
    ['managers', 'managerFilters'],
    'managers_',
);

const {
    search: candidateSearch,
    setFilter: filterCandidates,
    goToPage: goToCandidatesPage,
} = useListingFilters(
    managersIndex(application.id).url,
    candidateFilters,
    ['candidates', 'candidateFilters'],
    'candidates_',
);

const columns: DataTableColumn[] = [
    { id: 'user', header: 'User', alwaysVisible: true },
    { id: 'actions', header: '', align: 'right', alwaysVisible: true },
];

function assign(userId: number) {
    router.post(addManager(application.id).url, { user_id: userId });
}

function remove(manager: Manager) {
    router.delete(removeManager([application.id, manager.id]).url);
}
</script>

<template>
    <Head :title="`${application.name} managers`" />

    <ApplicationLayout
        :application="application"
        :sections="sections"
        description="Who may configure this application, rotate its secret and read its history"
        :can-manage="canManageApplication"
    >
        <Card>
            <CardHeader>
                <CardTitle>Managers</CardTitle>
                <CardDescription>
                    A manager can edit this application, rotate its client
                    secret, define the roles its tokens carry, and read its
                    audit trail. They cannot enable or disable it, decide who
                    may sign in to it, or see any other application.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <DataTable
                    :columns="columns"
                    :rows="managers.data"
                    :row-key="(manager) => manager.id"
                    :empty="
                        managerFilters.search
                            ? 'No manager matches this search.'
                            : 'Nobody looks after this application yet. An administrator maintains it until somebody is assigned.'
                    "
                >
                    <template #toolbar>
                        <SearchInput
                            v-model="managerSearch"
                            placeholder="Search by name or email"
                            label="Search managers"
                        />
                    </template>

                    <template #cell-user="{ row }">
                        <div class="flex items-center gap-2 font-medium">
                            {{ row.name }}
                            <Badge v-if="row.disabled" variant="destructive">
                                Disabled
                            </Badge>
                        </div>
                        <div class="text-muted-foreground truncate text-sm">
                            {{ row.email }}
                        </div>
                    </template>

                    <template #cell-actions="{ row }">
                        <DangerousAction
                            title="Remove this manager?"
                            :description="`${row.name} will lose the ability to configure ${application.name}. Their access to sign in, if they have any, is unaffected.`"
                            confirm-label="Remove manager"
                            @confirm="remove(row)"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Remove ${row.name} as a manager`"
                            >
                                <X class="size-4" />
                            </Button>
                        </DangerousAction>
                    </template>

                    <template #footer>
                        <Pagination
                            :paginator="managers"
                            @update:page="goToManagersPage"
                            @update:per-page="
                                (size) => filterManagers('per_page', size)
                            "
                        />
                    </template>
                </DataTable>
            </CardContent>
        </Card>

        <CandidatePicker
            v-model:search="candidateSearch"
            title="Add a manager"
            description="Only people holding the Developer role can be assigned: without it the assignment would grant them nothing."
            search-label="Search developers to assign"
            action="Assign"
            :candidates="candidates"
            empty="No developer matches this search, or everyone matching already looks after this application."
            @select="assign"
            @update:page="goToCandidatesPage"
            @update:per-page="(size) => filterCandidates('per_page', size)"
        />
    </ApplicationLayout>
</template>
