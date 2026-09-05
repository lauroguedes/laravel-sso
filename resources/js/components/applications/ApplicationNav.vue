<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { audit as applicationAudit, show } from '@/routes/applications';
import { index as grants } from '@/routes/applications/grants';
import { index as roles } from '@/routes/applications/roles';
import { toUrl } from '@/lib/utils';

/**
 * Moves between the pages that make up one application: its configuration,
 * the roles it defines, and who may sign in to it.
 */
const { applicationId } = defineProps<{
    applicationId: string;
}>();

/*
 * All three are leaf URLs, so they match exactly. Prefix matching would light
 * up Overview on the other two, since they live beneath it.
 */
const items = [
    { title: 'Overview', href: show(applicationId) },
    { title: 'Roles', href: roles(applicationId) },
    { title: 'Access', href: grants(applicationId) },
    { title: 'Audit', href: applicationAudit(applicationId) },
];

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-1" aria-label="Application">
        <Button
            v-for="item in items"
            :key="toUrl(item.href)"
            variant="ghost"
            size="sm"
            :class="{ 'bg-muted': isCurrentUrl(item.href) }"
            as-child
        >
            <Link :href="item.href">{{ item.title }}</Link>
        </Button>
    </nav>
</template>
