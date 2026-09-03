<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { ScopeOption } from '@/types/administration';

/**
 * Chooses the scopes an application is permitted to request.
 *
 * Scopes describe what an application may ask for; they are not roles and
 * carry no permissions of their own. A request for a scope that is not
 * selected here is dropped when the token is issued.
 */
defineProps<{
    scopes: ScopeOption[];
    selected: string[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="scope in scopes"
            :key="scope.id"
            class="flex items-start gap-3"
        >
            <Checkbox
                :id="`scope-${scope.id}`"
                name="scopes[]"
                :value="scope.id"
                :default-value="selected.includes(scope.id)"
            />
            <div class="grid gap-0.5">
                <Label :for="`scope-${scope.id}`" class="font-mono font-normal">
                    {{ scope.id }}
                </Label>
                <p class="text-muted-foreground text-sm">
                    {{ scope.description }}
                </p>
            </div>
        </div>

        <InputError :message="errors.scopes" />
    </div>
</template>
