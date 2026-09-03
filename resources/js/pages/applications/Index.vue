<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationLink } from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, index, show } from '@/routes/applications';
import type { ApplicationSummary } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { applications, filters } = defineProps<{
    applications: {
        data: ApplicationSummary[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { search: string | null };
}>();

const search = ref(filters.search ?? '');

let debounce: ReturnType<typeof setTimeout> | undefined;

watch(search, (value) => {
    clearTimeout(debounce);

    debounce = setTimeout(() => {
        router.get(
            index().url,
            { search: value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Applications" />

    <div class="px-4 py-6">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Applications"
                description="OAuth2 and OpenID Connect clients that authenticate through this server"
            />

            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Register application
                </Link>
            </Button>
        </div>

        <div class="relative mb-4 max-w-sm">
            <Search
                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
            />
            <Input
                v-model="search"
                type="search"
                class="pl-9"
                placeholder="Search by name or client ID"
                aria-label="Search applications"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Client ID</TableHead>
                        <TableHead class="text-right">Status</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableEmpty
                        v-if="applications.data.length === 0"
                        :colspan="4"
                    >
                        No applications match this search.
                    </TableEmpty>

                    <TableRow
                        v-for="application in applications.data"
                        :key="application.id"
                        class="cursor-pointer"
                        @click="router.visit(show(application.id))"
                    >
                        <TableCell>
                            <Link
                                :href="show(application.id)"
                                class="font-medium hover:underline"
                                @click.stop
                            >
                                {{ application.name }}
                            </Link>
                            <div
                                v-if="application.description"
                                class="text-muted-foreground line-clamp-1 text-sm"
                            >
                                {{ application.description }}
                            </div>
                        </TableCell>

                        <TableCell class="text-sm">
                            {{ application.type_label }}
                        </TableCell>

                        <TableCell>
                            <code class="text-muted-foreground text-xs">
                                {{ application.id }}
                            </code>
                        </TableCell>

                        <TableCell class="text-right">
                            <Badge
                                :variant="
                                    application.enabled
                                        ? 'secondary'
                                        : 'destructive'
                                "
                            >
                                {{ application.enabled ? 'Enabled' : 'Disabled' }}
                            </Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination
            :links="applications.links"
            :from="applications.from"
            :to="applications.to"
            :total="applications.total"
        />
    </div>
</template>
