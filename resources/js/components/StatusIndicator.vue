<script setup lang="ts">
import { CircleCheck, CircleSlash } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Whether something is in service, as a dot rather than a word.
 *
 * A listing is scanned, not read: a column of repeated "Enabled" badges costs
 * as much width as the name beside it and is slower to skim than a row of
 * green. The wording survives in the tooltip and in the accessible name, so
 * nothing is lost to a screen reader or to a reader who is unsure.
 */
const { active, activeLabel, inactiveLabel } = defineProps<{
    active: boolean;
    activeLabel: string;
    inactiveLabel: string;
}>();
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <Tooltip>
            <TooltipTrigger as-child>
                <span
                    class="inline-flex"
                    role="img"
                    :aria-label="active ? activeLabel : inactiveLabel"
                >
                    <CircleCheck
                        v-if="active"
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                    <CircleSlash v-else class="text-destructive size-4" />
                </span>
            </TooltipTrigger>

            <TooltipContent>
                {{ active ? activeLabel : inactiveLabel }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
