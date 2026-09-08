<script setup lang="ts">
import { Check, Filter } from '@lucide/vue';
import { computed } from 'vue';
import IconButton from '@/components/IconButton.vue';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type FilterGroup = {
    /** The query parameter this group sets. */
    key: string;
    label: string;
    options: { value: string; label: string }[];
};

/**
 * Narrows a listing by one value from each of several groups.
 *
 * One menu rather than a control per group: a listing usually has two or three
 * of these, and a row of dropdowns competes with the search box for the space
 * above the table. The count on the trigger says how many are active, so the
 * menu never hides the fact that a list is filtered.
 */
const { groups, active } = defineProps<{
    groups: FilterGroup[];
    active: Record<string, string | number | null | undefined>;
}>();

const emit = defineEmits<{ change: [key: string, value: string | null] }>();

const activeCount = computed(
    () => groups.filter((group) => Boolean(active[group.key])).length,
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <span class="relative inline-flex">
                <IconButton
                    :label="
                        activeCount > 0
                            ? `Filters (${activeCount} active)`
                            : 'Filters'
                    "
                    :icon="Filter"
                    variant="outline"
                />
                <Badge
                    v-if="activeCount > 0"
                    class="pointer-events-none absolute -top-1.5 -right-1.5 size-4 justify-center p-0 text-[10px]"
                >
                    {{ activeCount }}
                </Badge>
            </span>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-52">
            <template v-for="(group, position) in groups" :key="group.key">
                <DropdownMenuSeparator v-if="position > 0" />
                <DropdownMenuLabel>{{ group.label }}</DropdownMenuLabel>

                <DropdownMenuItem @select="emit('change', group.key, null)">
                    <Check
                        class="size-4"
                        :class="active[group.key] ? 'opacity-0' : ''"
                    />
                    All
                </DropdownMenuItem>

                <DropdownMenuItem
                    v-for="option in group.options"
                    :key="option.value"
                    @select="emit('change', group.key, option.value)"
                >
                    <Check
                        class="size-4"
                        :class="
                            String(active[group.key]) === option.value
                                ? ''
                                : 'opacity-0'
                        "
                    />
                    {{ option.label }}
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
