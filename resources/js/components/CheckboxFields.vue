<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export type CheckboxOption = {
    value: string;
    label: string;
    description?: string | null;
};

/**
 * A list of checkboxes submitted as one array field.
 *
 * Used wherever the interface grants a set of named things — application
 * scopes, platform roles, the permissions a role carries — so that they all
 * submit and render the same way.
 */
defineProps<{
    name: string;
    options: CheckboxOption[];
    selected: string[];
    errors: Record<string, string>;
    errorKey: string;
    disabled?: boolean;
    mono?: boolean;
    emptyMessage?: string;
}>();
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="option in options"
            :key="option.value"
            class="flex items-start gap-3"
        >
            <Checkbox
                :id="`${name}-${option.value}`"
                :name="`${name}[]`"
                :value="option.value"
                :default-value="selected.includes(option.value)"
                :disabled="disabled"
            />
            <div class="grid gap-0.5">
                <Label
                    :for="`${name}-${option.value}`"
                    :class="['font-normal', mono ? 'font-mono' : '']"
                >
                    {{ option.label }}
                </Label>
                <p
                    v-if="option.description"
                    class="text-muted-foreground text-sm"
                >
                    {{ option.description }}
                </p>
            </div>
        </div>

        <p
            v-if="options.length === 0 && emptyMessage"
            class="text-muted-foreground text-sm"
        >
            {{ emptyMessage }}
        </p>

        <InputError :message="errors[errorKey]" />
    </div>
</template>
