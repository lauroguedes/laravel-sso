<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { Button } from '@/components/ui/button';

const { value, label = 'Copy' } = defineProps<{
    value: string;
    label?: string;
}>();

const { copy, copied, isSupported } = useClipboard({ copiedDuring: 2000 });
</script>

<template>
    <Button
        v-if="isSupported"
        type="button"
        variant="outline"
        size="sm"
        @click="copy(value)"
    >
        <Check v-if="copied" class="size-4" />
        <Copy v-else class="size-4" />
        {{ copied ? 'Copied' : label }}
    </Button>
</template>
