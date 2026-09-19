<script setup lang="ts">
import { ChevronLeft, ChevronRight, Rows3 } from '@lucide/vue';
import {
    Pagination as PaginationRoot,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginator } from '@/types/administration';

/**
 * Pages a listing: what is shown and how many rows on the left, the pages
 * themselves on the right.
 *
 * Every listing here is counted, so every page is offered by number.
 *
 * Which page is asked for is the listing's own business, so both controls
 * report upwards rather than visiting: useListingFilters names the parameter
 * and keeps the other listings on the page where they were.
 */
const { sizes = true } = defineProps<{
    paginator: Paginator<unknown>;
    /** Offer the rows-per-page selector, for a list that can grow long. */
    sizes?: boolean;
}>();

const emit = defineEmits<{
    'update:page': [number];
    'update:perPage': [number];
}>();

/** Matches InterfaceOptions::PAGE_SIZES, which validates the request. */
const options = [15, 25, 50, 100];
</script>

<template>
    <div
        v-if="paginator.total > 0"
        class="flex flex-wrap items-center justify-between gap-4"
    >
        <div class="text-muted-foreground flex items-center gap-3 text-sm">
            <span>
                Showing {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} of
                {{ paginator.total }}
            </span>

            <Select
                v-if="sizes"
                :model-value="String(paginator.per_page)"
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
                        v-for="size in options"
                        :key="size"
                        :value="String(size)"
                    >
                        {{ size }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <PaginationRoot
            v-if="paginator.last_page > 1"
            v-slot="{ page: current }"
            :page="paginator.current_page"
            :total="paginator.total"
            :items-per-page="paginator.per_page"
            :sibling-count="1"
            show-edges
            class="mx-0 w-auto"
            @update:page="(target: number) => emit('update:page', target)"
        >
            <PaginationContent v-slot="{ items }">
                <PaginationPrevious size="icon" aria-label="Previous page">
                    <ChevronLeft />
                </PaginationPrevious>

                <template v-for="(item, index) in items" :key="index">
                    <PaginationItem
                        v-if="item.type === 'page'"
                        :value="item.value"
                        :is-active="item.value === current"
                    >
                        {{ item.value }}
                    </PaginationItem>

                    <PaginationEllipsis v-else :index="index" />
                </template>

                <PaginationNext size="icon" aria-label="Next page">
                    <ChevronRight />
                </PaginationNext>
            </PaginationContent>
        </PaginationRoot>
    </div>
</template>
