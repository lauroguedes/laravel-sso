<script setup lang="ts">
import { computed } from 'vue';
import IconButton from '@/components/IconButton.vue';
import { Check, Copy } from '@lucide/vue';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useCopyToClipboard } from '@/composables/useCopyToClipboard';

/**
 * A block of code, with the means to take it away.
 *
 * shadcn-vue's registry has no code block — the one on its own documentation
 * site belongs to that site — so this is assembled from the parts it does
 * ship: a scroll area for the overflow, and the same icon button every other
 * control on the page uses.
 *
 * The copy action sits in a header bar rather than floating over the first
 * line. Floating meant it covered whatever happened to be underneath, and the
 * bar gives the block somewhere to say what it is.
 */
const { code, label } = defineProps<{
    /** The text a reader copies, unhighlighted. */
    code: string;
    /** Coloured markup for the same text. Escaped by whoever produced it. */
    highlighted: string;
    /** What this block is, shown in the bar. */
    label: string;
    copyLabel?: string;
}>();

const {
    copy,
    copied,
    label: tooltip,
} = useCopyToClipboard(() => `Copy the ${label.toLowerCase()}`);
</script>

<template>
    <div class="bg-muted/50 overflow-hidden rounded-lg border">
        <div
            class="flex items-center justify-between gap-2 border-b py-1 pr-1 pl-3"
        >
            <span class="text-muted-foreground font-mono text-xs">
                {{ label }}
            </span>

            <IconButton
                :label="tooltip"
                :icon="copied ? Check : Copy"
                size="icon-sm"
                @click="copy(code)"
            />
        </div>

        <!--
            The bound goes on the viewport, not the frame: the frame has no
            height of its own, so a maximum there would clip the document
            rather than let it scroll.
        -->
        <ScrollArea horizontal viewport-class="max-h-[50vh]">
            <pre
                class="w-max min-w-full p-4 font-mono text-xs leading-relaxed"
            ><code v-html="highlighted" /></pre>
        </ScrollArea>
    </div>
</template>
