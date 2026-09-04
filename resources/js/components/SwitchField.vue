<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

/**
 * One labelled boolean setting, submitted as part of the surrounding form.
 *
 * The switch submits "1" when on and nothing when off, which is what Laravel's
 * boolean validation and Request::boolean() expect.
 */
defineProps<{
    name: string;
    label: string;
    description: string;
    defaultValue: boolean;
    errors: Record<string, string>;
}>();
</script>

<template>
    <div>
        <div class="flex items-start justify-between gap-4">
            <div class="grid gap-1">
                <Label :for="name">{{ label }}</Label>
                <p class="text-muted-foreground text-sm">{{ description }}</p>
            </div>

            <Switch
                :id="name"
                :name="name"
                value="1"
                :default-value="defaultValue"
            />
        </div>

        <InputError class="mt-2" :message="errors[name]" />
    </div>
</template>
