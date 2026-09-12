<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

/**
 * This server's mark: an uploaded logo when there is one, the built-in icon
 * otherwise.
 *
 * One component so the sign-in page, the sidebar and the consent screen cannot
 * end up showing different marks after a rebrand.
 *
 * "badge" sits the icon on a coloured square at a fixed size. "bare" is the
 * mark alone, sized by whoever places it.
 */
const { size = 'sm', variant = 'badge' } = defineProps<{
    size?: 'sm' | 'lg';
    variant?: 'badge' | 'bare';
}>();

const branding = computed(() => usePage().props.branding);

const box = computed(() => ({ sm: 'size-8', lg: 'size-10' })[size]);

const glyph = computed(() => ({ sm: 'size-5', lg: 'size-6' })[size]);
</script>

<template>
    <img
        v-if="variant === 'bare' && branding.logo"
        :src="branding.logo"
        alt=""
    />

    <AppLogoIcon v-else-if="variant === 'bare'" />

    <img
        v-else-if="branding.logo"
        :src="branding.logo"
        :alt="branding.name"
        class="rounded-md object-contain"
        :class="box"
    />

    <div
        v-else
        class="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square items-center justify-center rounded-md"
        :class="box"
    >
        <AppLogoIcon class="fill-current" :class="glyph" />
    </div>
</template>
