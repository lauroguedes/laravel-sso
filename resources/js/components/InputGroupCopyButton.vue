<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import InputGroupIconButton from '@/components/InputGroupIconButton.vue';
import { useCopyToClipboard } from '@/composables/useCopyToClipboard';

/**
 * CopyButton for a value shown in a field, where the control belongs inside
 * the border rather than trailing after it.
 */
const { value, label = 'Copy' } = defineProps<{
    value: string;
    label?: string;
}>();

const {
    copy,
    copied,
    isSupported,
    label: tooltip,
} = useCopyToClipboard(() => label);
</script>

<template>
    <InputGroupIconButton
        v-if="isSupported"
        :label="tooltip"
        :icon="copied ? Check : Copy"
        @click="copy(value)"
    />
</template>
