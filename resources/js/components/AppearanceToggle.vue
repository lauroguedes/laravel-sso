<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import IconButton from '@/components/IconButton.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';

/**
 * Switches between light, dark and following the system.
 *
 * Lives in the application header rather than on a settings page: it is a
 * per-device preference someone changes when the light in the room changes,
 * not an account setting they go looking for once.
 */
const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
] as const;

const current = computed(
    () =>
        options.find((option) => option.value === appearance.value) ??
        options[2],
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <span>
                <IconButton
                    :label="`Theme: ${current.label}`"
                    :icon="current.icon"
                />
            </span>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end">
            <DropdownMenuItem
                v-for="option in options"
                :key="option.value"
                :class="{ 'bg-muted': appearance === option.value }"
                @select="updateAppearance(option.value)"
            >
                <component :is="option.icon" class="size-4" />
                {{ option.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
