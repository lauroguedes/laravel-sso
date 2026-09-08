<script setup lang="ts">
import { ref } from 'vue';
import SettingsField from '@/components/settings/SettingsField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * One uploaded image, with the option of taking it away again.
 *
 * Removal is a flag rather than an immediate action: nothing is deleted until
 * the section is saved, so a reader who changes their mind can simply not
 * save. The server resolves the file field and the flag into one stored path,
 * because only it knows what the previous file was.
 */
defineProps<{
    name: string;
    label: string;
    description: string;
    accept: string;
    /** Whether an image is already stored, which is what makes removal offerable. */
    stored: boolean;
    error?: string;
}>();

const removing = ref(false);
</script>

<template>
    <SettingsField
        :id="name"
        :label="label"
        :description="description"
        :error="error"
    >
        <div class="flex flex-wrap items-center gap-3">
            <slot v-if="stored && !removing" name="preview" />

            <Input
                :id="name"
                :name="name"
                type="file"
                :accept="accept"
                class="max-w-xs"
            />

            <Button
                v-if="stored && !removing"
                type="button"
                variant="ghost"
                size="sm"
                @click="removing = true"
            >
                Remove
            </Button>

            <span v-else-if="removing" class="text-muted-foreground text-sm">
                Will be removed when you save.
            </span>
        </div>

        <input
            type="hidden"
            :name="`remove_${name}`"
            :value="removing ? '1' : '0'"
        />
    </SettingsField>
</template>
