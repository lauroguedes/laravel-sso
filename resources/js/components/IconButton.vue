<script setup lang="ts">
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * A control that shows only its icon, and names itself on hover.
 *
 * The label is not dropped, it moves: it becomes the tooltip and the
 * accessible name, so the button stays findable by screen reader and by
 * keyboard while costing a row of a table almost no width.
 */
const {
    label,
    icon,
    variant = 'ghost',
    size = 'icon',
    disabled = false,
} = defineProps<{
    label: string;
    icon: Component;
    variant?: 'default' | 'ghost' | 'outline' | 'secondary' | 'destructive';
    size?: 'icon' | 'sm';
    disabled?: boolean;
}>();

defineEmits<{ click: [MouseEvent] }>();
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    type="button"
                    :variant="variant"
                    :size="size"
                    :disabled="disabled"
                    :aria-label="label"
                    @click="$emit('click', $event)"
                >
                    <component :is="icon" class="size-4" />
                </Button>
            </TooltipTrigger>

            <TooltipContent>{{ label }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
