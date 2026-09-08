<script setup lang="ts">
import InputGroupCopyButton from '@/components/InputGroupCopyButton.vue';
import { Label } from '@/components/ui/label';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';

/**
 * A value the reader takes away rather than edits: a client ID, an issuer, a
 * registered URI.
 *
 * A real, read-only input rather than a code block, so it selects, scrolls and
 * tabs the way every other field on the page does — and so the actions that
 * belong to the value sit inside its border instead of trailing after it.
 *
 * That makes the name mandatory rather than decorative: a focusable control
 * with no accessible name is announced as nothing at all, and these appear in
 * lists where one row looks exactly like the next.
 *
 * `copyable` needs its explicit default: Vue casts an absent Boolean prop to
 * false rather than leaving it undefined, so without one the copy control
 * would never render.
 */
const { copyable = true, copyLabel = 'Copy' } = defineProps<{
    /** Names the field. Shown above it unless `labelHidden`. */
    label: string;
    value: string;
    labelHidden?: boolean;
    copyable?: boolean;
    copyLabel?: string;
}>();
</script>

<template>
    <div class="grid gap-1.5">
        <Label v-if="!labelHidden" class="text-muted-foreground">
            {{ label }}
        </Label>

        <InputGroup>
            <InputGroupInput
                :model-value="value"
                readonly
                spellcheck="false"
                class="font-mono"
                :aria-label="label"
            />

            <InputGroupAddon align="inline-end">
                <!--
                    Mounted only when there is something to copy, so a masked
                    secret does not pay for clipboard permissions it will never
                    use.
                -->
                <InputGroupCopyButton
                    v-if="copyable"
                    :value="value"
                    :label="copyLabel"
                />

                <slot />
            </InputGroupAddon>
        </InputGroup>
    </div>
</template>
