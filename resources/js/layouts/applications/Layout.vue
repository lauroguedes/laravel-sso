<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import ApplicationTypeBadge from '@/components/applications/ApplicationTypeBadge.vue';
import Heading from '@/components/Heading.vue';
import IconButton from '@/components/IconButton.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
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
            <!--
                The status reads as an icon beside the name rather than a badge
                across the page: it belongs to the application, so it sits with
                what it describes.
            -->
            <div class="flex items-start gap-2">
                <span class="mt-1">
                    <StatusIndicator
                        :active="application.enabled"
                        active-label="Enabled"
                        inactive-label="Disabled"
                    />
                </span>

                <Heading :title="application.name" :description="description">
                    <template #title-suffix>
                        <ApplicationTypeBadge
                            :type="application.type"
                            :label="application.type_label"
                            :description="application.type_description"
                        />
                    </template>
                </Heading>
            </div>

            <IconButton
                v-if="canManage"
                label="Edit application"
                :icon="Pencil"
                variant="outline"
                @click="router.visit(edit(application.id))"
            />
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
