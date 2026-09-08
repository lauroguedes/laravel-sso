<script setup lang="ts">
import { CircleQuestionMark, Lock } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * One labelled setting, with its explanation and its error underneath.
 *
 * One explanation, shown one of two ways. Inline by default, under the field,
 * for a setting whose consequences a reader should not have to hover to
 * discover. Folded into a mark beside the label where a group of fields is
 * dense enough that a sentence under each one triples the height of the form
 * and pushes the next field off the screen.
 *
 * One prop rather than two, so a caller cannot ask for both and get the
 * sentence twice.
 *
 * A field the deployment pinned through the environment is shown rather than
 * hidden, and shown as fixed: the value is in force either way, and an
 * administrator looking for it deserves to find out why they cannot change it.
 */
const { explain = 'inline' } = defineProps<{
    id?: string;
    label: string;
    description?: string;
    /** Where the description goes: under the field, or behind a mark. */
    explain?: 'inline' | 'tooltip';
    error?: string;
    pinned?: boolean;
}>();
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center gap-1.5">
            <Label :for="id">{{ label }}</Label>

            <TooltipProvider
                v-if="description && explain === 'tooltip'"
                :delay-duration="150"
            >
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/50 rounded-full transition-colors focus-visible:ring-3 focus-visible:outline-none"
                            :aria-label="`What ${label} means`"
                        >
                            <CircleQuestionMark class="size-3.5" />
                        </button>
                    </TooltipTrigger>

                    <TooltipContent class="max-w-xs">
                        {{ description }}
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <span
                v-if="pinned"
                class="text-muted-foreground flex items-center gap-1 text-xs"
            >
                <Lock class="size-3" />
                Set by this deployment
            </span>
        </div>

        <slot />

        <p
            v-if="description && explain === 'inline'"
            class="text-muted-foreground text-sm"
        >
            {{ description }}
        </p>

        <InputError :message="error" />
    </div>
</template>
