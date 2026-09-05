<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import RecentActivityCard from '@/components/audit/RecentActivityCard.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { index as applications } from '@/routes/applications';
import { index as audit } from '@/routes/audit';
import { index as sessions } from '@/routes/sessions';
import { index as users } from '@/routes/users';
import type { AuditEntry, DashboardCounts } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const { counts } = defineProps<{
    counts: DashboardCounts;
    recent: { security: AuditEntry[]; administration: AuditEntry[] } | null;
}>();

const tiles = [
    { label: 'Applications', value: counts.applications, href: applications() },
    { label: 'Users', value: counts.users, href: users() },
    { label: 'Disabled users', value: counts.disabled_users, href: users() },
    { label: 'Browser sessions', value: counts.sessions, href: sessions() },
    { label: 'Issued tokens', value: counts.tokens, href: sessions() },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="px-4 py-6">
        <Heading
            title="Dashboard"
            description="The current state of this Identity Provider"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Link
                v-for="tile in tiles"
                :key="tile.label"
                :href="tile.href"
                class="hover:bg-muted/50 rounded-xl border p-4 transition-colors"
            >
                <div class="text-muted-foreground text-sm">
                    {{ tile.label }}
                </div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ tile.value }}
                </div>
            </Link>
        </div>

        <div v-if="recent" class="grid gap-4 lg:grid-cols-2">
            <RecentActivityCard
                title="Recent security activity"
                :entries="recent.security"
                causer-fallback="Not signed in"
                show-address
            />

            <RecentActivityCard
                title="Recent administration"
                :entries="recent.administration"
                causer-fallback="System"
            />
        </div>

        <Card v-else>
            <CardContent class="text-muted-foreground py-6 text-sm">
                Recent activity is shown to administrators who can read the
                audit trail.
                <Link :href="audit()" class="underline">View audit</Link>
            </CardContent>
        </Card>
    </div>
</template>
