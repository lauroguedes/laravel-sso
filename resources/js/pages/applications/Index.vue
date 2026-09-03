<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationLink } from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import SearchInput from '@/components/SearchInput.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useSearchFilter } from '@/composables/useSearchFilter';
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

const { search } = useSearchFilter(index().url, filters.search);
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

        <SearchInput
            v-model="search"
            class="mb-4"
            placeholder="Search by name or client ID"
            label="Search applications"
        />

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
                                {{
                                    application.enabled ? 'Enabled' : 'Disabled'
                                }}
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
