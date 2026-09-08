<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editApplicationSettings } from '@/routes/application-settings';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

/*
 * App settings describe the whole installation rather than the reader, so the
 * tab only exists for someone who may change them.
 */
const page = usePage();

const sidebarNavItems = computed<NavItem[]>(() => [
    { title: 'Profile', href: editProfile() },
    { title: 'Security', href: editSecurity() },
    ...(page.props.can.manageSettings
        ? [{ title: 'App settings', href: editApplicationSettings() }]
        : []),
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="mx-auto w-full max-w-5xl px-4 py-8">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="min-w-0 flex-1">
                <section class="max-w-2xl space-y-8">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
