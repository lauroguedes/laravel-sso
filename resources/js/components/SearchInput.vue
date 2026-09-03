<script setup lang="ts">
import { Search } from '@lucide/vue';
import { Input } from '@/components/ui/input';

const search = defineModel<string>({ required: true });

defineProps<{
    placeholder: string;
    label: string;
}>();
</script>

<template>
    <div class="relative max-w-sm">
        <Search
            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <Input
            v-model="search"
            type="search"
            class="pl-9"
            :placeholder="placeholder"
            :aria-label="label"
        />
    </div>
</template>
