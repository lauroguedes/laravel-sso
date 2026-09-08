<script setup lang="ts">
import type { Component } from 'vue';
import { InputGroupButton } from '@/components/ui/input-group';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * IconButton's sibling for a control that lives inside a field's border.
 *
 * Same bargain — the label becomes the tooltip and the accessible name — but
 * built on InputGroupButton, which is sized and shaped to sit within an input
 * rather than beside one.
 */
defineProps<{
    label: string;
    icon: Component;
}>();

defineEmits<{ click: [MouseEvent] }>();
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <Tooltip>
            <TooltipTrigger as-child>
                <InputGroupButton
                    type="button"
                    size="icon-xs"
                    :aria-label="label"
                    @click="$emit('click', $event)"
                >
                    <component :is="icon" />
                </InputGroupButton>
            </TooltipTrigger>

            <TooltipContent>{{ label }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
