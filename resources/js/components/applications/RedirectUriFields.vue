<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Edits the list of redirect URIs registered for an application.
 *
 * Each URI is a separate field submitted as "redirect_uris[]", so the server
 * validates and stores them individually. They are matched exactly when an
 * authorization request arrives, which is why there is no pattern field.
 */
const { modelValue, errors } = defineProps<{
    modelValue: string[];
    errors: Record<string, string>;
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
                <Label :for="`redirect-uri-${position}`" class="sr-only">
                    Redirect URI {{ position + 1 }}
                </Label>
                <Input
                    :id="`redirect-uri-${position}`"
                    v-model="uris[position]"
                    name="redirect_uris[]"
                    type="url"
                    inputmode="url"
                    spellcheck="false"
                    placeholder="https://app.example.com/auth/callback"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="`Remove redirect URI ${position + 1}`"
                    @click="remove(position)"
                >
                    <X class="size-4" />
                </Button>
            </div>

            <InputError :message="errors[`redirect_uris.${position}`]" />
        </div>

        <InputError :message="errors.redirect_uris" />

        <Button type="button" variant="outline" size="sm" @click="add">
            <Plus class="size-4" />
            Add redirect URI
        </Button>

        <p class="text-muted-foreground text-sm">
            URIs are matched exactly, so wildcards are not accepted. HTTPS is
            required except on localhost.
        </p>
    </div>
</template>
