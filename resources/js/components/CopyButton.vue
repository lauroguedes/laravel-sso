<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed } from 'vue';
import IconButton from '@/components/IconButton.vue';

/**
 * Copies a value, and says what it copied on hover.
 *
 * Icon only: these sit beside credentials and URIs where the value beside them
 * is what the reader is looking at, and a repeated "Copy" word competes with
 * it. The label survives as the tooltip and the accessible name.
 */
const { value, label = 'Copy' } = defineProps<{
    value: string;
    label?: string;
}>();

const { copy, copied, isSupported } = useClipboard({ copiedDuring: 2000 });

const tooltip = computed(() => (copied.value ? 'Copied' : label));
</script>

<template>
    <IconButton
        v-if="isSupported"
        :label="tooltip"
        :icon="copied ? Check : Copy"
        variant="outline"
        @click="copy(value)"
    />
</template>
