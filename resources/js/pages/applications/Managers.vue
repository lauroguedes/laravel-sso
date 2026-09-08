<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import ApplicationLayout from '@/layouts/applications/Layout.vue';
import CandidatePicker from '@/components/applications/CandidatePicker.vue';
import DangerousAction from '@/components/DangerousAction.vue';
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
import { index } from '@/routes/applications';
import {
    destroy as removeManager,
    index as managersIndex,
    store as addManager,
} from '@/routes/applications/managers';
import type {
    ApplicationHeader,
    ApplicationSection,
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

const { application, filters } = defineProps<{
    application: ApplicationHeader;
    sections: ApplicationSection[];
    canManageApplication: boolean;
    filters: { search: string | null };
    managers: Manager[];
    candidates: UserCandidate[];
}>();

const { search } = useListingFilters(
    managersIndex(application.id).url,
    filters,
    ['candidates', 'filters'],
);

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
                <ul
                    v-if="managers.length > 0"
                    class="divide-y rounded-lg border"
                >
                    <li
                        v-for="manager in managers"
                        :key="manager.id"
                        class="flex items-center justify-between gap-3 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 font-medium">
                                {{ manager.name }}
                                <Badge
                                    v-if="manager.disabled"
                                    variant="destructive"
                                >
                                    Disabled
                                </Badge>
                            </div>
                            <div class="text-muted-foreground truncate text-sm">
                                {{ manager.email }}
                            </div>
                        </div>

                        <DangerousAction
                            title="Remove this manager?"
                            :description="`${manager.name} will lose the ability to configure ${application.name}. Their access to sign in, if they have any, is unaffected.`"
                            confirm-label="Remove manager"
                            @confirm="remove(manager)"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Remove ${manager.name} as a manager`"
                            >
                                <X class="size-4" />
                            </Button>
                        </DangerousAction>
                    </li>
                </ul>

                <p v-else class="text-muted-foreground text-sm">
                    Nobody looks after this application yet. An administrator
                    maintains it until somebody is assigned.
                </p>
            </CardContent>
        </Card>

        <CandidatePicker
            v-model:search="search"
            title="Add a manager"
            description="Only people holding the Developer role can be assigned: without it the assignment would grant them nothing."
            search-label="Search developers to assign"
            action="Assign"
            :candidates="candidates"
            empty="No developer matches this search, or everyone matching already looks after this application."
            @select="assign"
        />
    </ApplicationLayout>
</template>
