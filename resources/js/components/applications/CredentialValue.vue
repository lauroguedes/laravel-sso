<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';

/**
 * One labelled, monospaced value from an application's credentials, with an
 * optional copy control. A masked secret passes `copyable: false`, since there
 * is nothing on screen worth copying.
 *
 * `copyable` needs its explicit default: Vue casts an absent Boolean prop to
 * false rather than leaving it undefined, so without one the copy control
 * would never render.
 */
const { copyable = true } = defineProps<{
    label: string;
    value: string;
    copyable?: boolean;
    copyLabel?: string;
}>();
</script>

<template>
    <div class="grid gap-1.5">
        <span class="text-muted-foreground text-sm">{{ label }}</span>

        <div class="flex flex-wrap items-center gap-2">
            <code
                class="bg-muted min-w-0 flex-1 overflow-x-auto rounded px-3 py-2 font-mono text-sm"
            >
                {{ value }}
            </code>

            <CopyButton v-if="copyable" :value="value" :label="copyLabel" />
            <slot />
        </div>
    </div>
</template>
