<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * Chooses the platform roles a user holds.
 *
 * These grant access to this administration console and are separate from any
 * role a user may hold inside an individual OAuth application.
 */
defineProps<{
    roles: string[];
    selected: string[];
    errors: Record<string, string>;
    disabled?: boolean;
}>();
</script>

<template>
    <div class="space-y-3">
        <div v-for="role in roles" :key="role" class="flex items-center gap-3">
            <Checkbox
                :id="`role-${role}`"
                name="roles[]"
                :value="role"
                :default-value="selected.includes(role)"
                :disabled="disabled"
            />
            <Label :for="`role-${role}`" class="font-normal">{{ role }}</Label>
        </div>

        <InputError :message="errors.roles" />
    </div>
</template>
