<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { audit, edit, show } from '@/routes/applications';
import type { ApplicationHeader } from '@/types/administration';
import { index as grants } from '@/routes/applications/grants';
import { index as roles } from '@/routes/applications/roles';

/**
 * The frame every page of one application shares.
 *
 * Built like the settings layout: one heading, a vertical rail of sections,
 * and the page's own content beside it. Holding the name, the status and the
 * Edit button here is what keeps the four sections consistent — each page used
 * to describe the application slightly differently, and the audit page did not
 * describe it at all.
 */
const { application, canManage = false } = defineProps<{
    application: ApplicationHeader;
    /** What this section is, shown under the application's name. */
    description: string;
    canManage?: boolean;
}>();

/*
 * All four are leaf URLs, so they match exactly. Prefix matching would light
 * up Overview on the other three, since they live beneath it.
 */
const sections = [
    { title: 'Overview', href: show(application.id) },
    { title: 'Roles', href: roles(application.id) },
    { title: 'Access', href: grants(application.id) },
    { title: 'Audit', href: audit(application.id) },
];

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <div class="mx-auto w-full max-w-5xl px-4 py-8">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <Heading :title="application.name" :description="description" />

            <div class="flex items-center gap-2">
                <Badge
                    :variant="application.enabled ? 'secondary' : 'destructive'"
                >
                    {{ application.enabled ? 'Enabled' : 'Disabled' }}
                </Badge>

                <Button v-if="canManage" variant="outline" as-child>
                    <Link :href="edit(application.id)">
                        <Pencil class="size-4" />
                        Edit
                    </Link>
                </Button>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Application"
                >
                    <Button
                        v-for="section in sections"
                        :key="toUrl(section.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentUrl(section.href) },
                        ]"
                        as-child
                    >
                        <Link :href="section.href">{{ section.title }}</Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="min-w-0 flex-1 space-y-6">
                <slot />
            </div>
        </div>
    </div>
</template>
