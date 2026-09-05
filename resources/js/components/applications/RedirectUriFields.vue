<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Edits a list of URIs registered for an application.
 *
 * Each URI is a separate field submitted as "<name>[]", so the server
 * validates and stores them individually. They are matched exactly when the
 * request arrives, which is why there is no pattern field.
 *
 * The same component serves the redirect URIs, which receive authorization
 * codes, and the post-logout URIs, which are where the browser may be sent
 * after signing out. They are separate lists on purpose.
 */
const {
    modelValue,
    errors,
    name = 'redirect_uris',
    label = 'redirect URI',
    placeholder = 'https://app.example.com/auth/callback',
} = defineProps<{
    modelValue: string[];
    errors: Record<string, string>;
    name?: string;
    label?: string;
    placeholder?: string;
}>();

const uris = ref<string[]>(modelValue.length > 0 ? [...modelValue] : ['']);

function add() {
    uris.value.push('');
}

function remove(index: number) {
    uris.value.splice(index, 1);

    if (uris.value.length === 0) {
        uris.value.push('');
    }
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="(uri, position) in uris" :key="position" class="space-y-2">
            <div class="flex items-center gap-2">
                <Label :for="`${name}-${position}`" class="sr-only">
                    {{ label }} {{ position + 1 }}
                </Label>
                <Input
                    :id="`${name}-${position}`"
                    v-model="uris[position]"
                    :name="`${name}[]`"
                    type="url"
                    inputmode="url"
                    spellcheck="false"
                    :placeholder="placeholder"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="`Remove ${label} ${position + 1}`"
                    @click="remove(position)"
                >
                    <X class="size-4" />
                </Button>
            </div>

            <InputError :message="errors[`${name}.${position}`]" />
        </div>

        <InputError :message="errors[name]" />

        <Button type="button" variant="outline" size="sm" @click="add">
            <Plus class="size-4" />
            Add {{ label }}
        </Button>

        <p class="text-muted-foreground text-sm">
            URIs are matched exactly, so wildcards are not accepted. HTTPS is
            required except on localhost.
        </p>
    </div>
</template>
