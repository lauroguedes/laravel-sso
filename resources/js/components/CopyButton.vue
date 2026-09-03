<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

const { value, label = 'Copy' } = defineProps<{
    value: string;
    label?: string;
}>();

const copied = ref(false);
let resetTimer: ReturnType<typeof setTimeout> | undefined;

async function copy() {
    try {
        await navigator.clipboard.writeText(value);
    } catch {
        // Clipboard access can be refused; leave the value on screen to copy by hand.
        return;
    }

    copied.value = true;
    clearTimeout(resetTimer);
    resetTimer = setTimeout(() => (copied.value = false), 2000);
}

onUnmounted(() => clearTimeout(resetTimer));
</script>

<template>
    <Button type="button" variant="outline" size="sm" @click="copy">
        <Check v-if="copied" class="size-4" />
        <Copy v-else class="size-4" />
        {{ copied ? 'Copied' : label }}
    </Button>
</template>
