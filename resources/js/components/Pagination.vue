<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * "total" is absent under simple pagination, which the audit trail uses to
 * avoid counting a table that grows with every sign-in.
 */
const { links, from, to, total } = defineProps<{
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total?: number;
}>();
</script>

<template>
    <nav
        v-if="(total ?? 0) > 0 || (from ?? 0) > 0"
        class="flex flex-wrap items-center justify-between gap-3 pt-4"
        aria-label="Pagination"
    >
        <p class="text-muted-foreground text-sm">
            Showing {{ from ?? 0 }}–{{ to ?? 0 }}
            <template v-if="total !== undefined">of {{ total }}</template>
        </p>

        <div v-if="links.length > 3" class="flex flex-wrap items-center gap-1">
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
