<script setup lang="ts">
import { Lock } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

/**
 * One labelled setting, with its explanation and its error underneath.
 *
 * A field the deployment pinned through the environment is shown rather than
 * hidden, and shown as fixed: the value is in force either way, and an
 * administrator looking for it deserves to find out why they cannot change it.
 */
defineProps<{
    id?: string;
    label: string;
    description?: string;
    error?: string;
    pinned?: boolean;
}>();
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center gap-2">
            <Label :for="id">{{ label }}</Label>

            <span
                v-if="pinned"
                class="text-muted-foreground flex items-center gap-1 text-xs"
            >
                <Lock class="size-3" />
                Set by this deployment
            </span>
        </div>

        <slot />

        <p v-if="description" class="text-muted-foreground text-sm">
            {{ description }}
        </p>

        <InputError :message="error" />
    </div>
</template>
