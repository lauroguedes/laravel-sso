<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { ApplicationSummary } from '@/types/administration';

/**
 * An application's kind, as a colour.
 *
 * The three types imply different security properties — whether a secret
 * exists, whether a user is present — and a reader scanning a listing should
 * be able to tell a service account from a browser app without reading. The
 * word stays; the colour just gets there first.
 */
const { type, description } = defineProps<{
    type: ApplicationSummary['type'];
    label: string;
    /** What this kind of client is, shown on hover. */
    description: string;
}>();

const tone = computed(
    () =>
        ({
            confidential:
                'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
            public: 'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
            machine:
                'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        })[type],
);
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <Tooltip>
            <TooltipTrigger as-child>
                <Badge variant="outline" :class="tone">{{ label }}</Badge>
            </TooltipTrigger>

            <TooltipContent class="max-w-xs">{{ description }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
