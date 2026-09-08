<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Rows3 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * Pages a listing, under either paginator.
 *
 * A length-aware paginator supplies "links" and "total"; the simple paginator
 * supplies neither, only the two neighbouring URLs. The audit trail uses the
 * simple one deliberately, to avoid counting a table that grows with every
 * sign-in, so this component has to read both shapes rather than assume the
 * one that happens to be commoner.
 */
const { links, from, to, total, prevPageUrl, nextPageUrl, perPage } =
    defineProps<{
        links?: PaginationLink[];
        from: number | null;
        to: number | null;
        total?: number;
        prevPageUrl?: string | null;
        nextPageUrl?: string | null;
        /** Omit to hide the size selector, for a list that is never long. */
        perPage?: number;
    }>();

const emit = defineEmits<{ 'update:perPage': [number] }>();

/** Matches InterfaceOptions::PAGE_SIZES, which validates the request. */
const sizes = [15, 25, 50, 100];

const hasNeighbours = computed(
    () => Boolean(prevPageUrl) || Boolean(nextPageUrl),
);
</script>

<template>
    <nav
        v-if="(total ?? 0) > 0 || (from ?? 0) > 0"
        class="flex flex-wrap items-center gap-4"
        aria-label="Pagination"
    >
        <div class="text-muted-foreground flex items-center gap-3 text-sm">
            <span>
                Showing {{ from ?? 0 }}–{{ to ?? 0 }}
                <template v-if="total !== undefined">of {{ total }}</template>
            </span>

            <Select
                v-if="perPage !== undefined"
                :model-value="String(perPage)"
                @update:model-value="emit('update:perPage', Number($event))"
            >
                <SelectTrigger
                    size="sm"
                    class="gap-1 px-2"
                    aria-label="Rows per page"
                >
                    <!-- The icon carries the meaning, so the options stay numbers. -->
                    <Rows3 class="text-muted-foreground size-4" />
                    <SelectValue />
                </SelectTrigger>

                <SelectContent>
                    <SelectItem
                        v-for="size in sizes"
                        :key="size"
                        :value="String(size)"
                    >
                        {{ size }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div v-if="hasNeighbours" class="flex flex-wrap items-center gap-1">
            <Button
                :variant="'ghost'"
                size="sm"
                :disabled="!prevPageUrl"
                as-child
            >
                <Link v-if="prevPageUrl" :href="prevPageUrl">Previous</Link>
                <span v-else>Previous</span>
            </Button>

            <Button
                :variant="'ghost'"
                size="sm"
                :disabled="!nextPageUrl"
                as-child
            >
                <Link v-if="nextPageUrl" :href="nextPageUrl">Next</Link>
                <span v-else>Next</span>
            </Button>
        </div>

        <div
            v-else-if="(links?.length ?? 0) > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template v-for="link in links" :key="link.label">
                <Button
                    v-if="link.url"
                    :variant="link.active ? 'secondary' : 'ghost'"
                    size="sm"
                    as-child
                >
                    <Link :href="link.url" v-html="link.label" />
                </Button>
                <span
                    v-else
                    class="text-muted-foreground px-2 text-sm"
                    v-html="link.label"
                />
            </template>
        </div>
    </nav>
</template>
