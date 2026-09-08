<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthCardLayout from '@/layouts/auth/AuthCardLayout.vue';
import AuthSimpleLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';

/**
 * The sign-in shell an administrator chose.
 *
 * All three take the same title and description, so the choice is a component
 * swap rather than three copies of every auth page.
 */
const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();

const layout = computed(
    () =>
        ({
            simple: AuthSimpleLayout,
            card: AuthCardLayout,
            split: AuthSplitLayout,
        })[usePage().props.branding.authLayout],
);
</script>

<template>
    <component :is="layout" :title="title" :description="description">
        <slot />
    </component>
</template>
