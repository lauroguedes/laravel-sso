<script setup lang="ts" generic="T extends { value: string }">
/**
 * A choice between a handful of options, all visible at once.
 *
 * Used where a select would hide what is being chosen: layouts need a sentence
 * to tell them apart, and colours mean nothing until you see them. Radio
 * inputs rather than buttons, so the group is one tab stop and the arrow keys
 * move within it.
 *
 * The caller draws each option through the slot; everything about selection,
 * submission and being pinned is here.
 */
defineProps<{
    name: string;
    modelValue: string;
    options: T[];
    /** Lay the cards out in a row rather than stacked. */
    inline?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [string] }>();
</script>

<template>
    <div :class="inline ? 'flex flex-wrap gap-2' : 'grid gap-2'">
        <label
            v-for="option in options"
            :key="option.value"
            class="hover:bg-muted/50 has-checked:border-primary has-checked:bg-muted/50 rounded-lg border transition-colors"
            :class="[
                inline
                    ? 'flex items-center gap-2 px-3 py-2 text-sm'
                    : 'flex items-start gap-3 p-3',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
            ]"
        >
            <input
                type="radio"
                :name="name"
                :value="option.value"
                :checked="modelValue === option.value"
                :disabled="disabled"
                :class="inline ? 'sr-only' : 'accent-primary mt-1'"
                @change="emit('update:modelValue', option.value)"
            />

            <slot :option="option" :checked="modelValue === option.value" />
        </label>
    </div>
</template>
