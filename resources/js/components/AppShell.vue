<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';

/**
 * The shell every signed-in page renders inside.
 *
 * Whether the menu is open is the server's answer, from a cookie, so the
 * sidebar does not flash open before the client reads its own preference.
 */
const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <SidebarProvider :default-open="isOpen">
        <slot />
    </SidebarProvider>
</template>
