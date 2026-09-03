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
import { create, edit, index } from '@/routes/users';
import type { UserSummary } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const { users, filters } = defineProps<{
    users: {
        data: UserSummary[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { search: string | null };
}>();

const { search } = useSearchFilter(index().url, filters.search);

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}
</script>

<template>
    <Head title="Users" />

    <div class="px-4 py-6">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Users"
                description="People who can authenticate through this Identity Provider"
            />

            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Add user
                </Link>
            </Button>
        </div>

        <SearchInput
            v-model="search"
            class="mb-4"
            placeholder="Search by name or email"
            label="Search users"
        />

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Roles</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Last sign-in</TableHead>
                        <TableHead class="text-right">Created</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableEmpty v-if="users.data.length === 0" :colspan="5">
                        No users match this search.
                    </TableEmpty>

                    <TableRow
                        v-for="user in users.data"
                        :key="user.id"
                        class="cursor-pointer"
                        @click="router.visit(edit(user.id))"
                    >
                        <TableCell>
                            <Link
                                :href="edit(user.id)"
                                class="font-medium hover:underline"
                                @click.stop
                            >
                                {{ user.name }}
                            </Link>
                            <div class="text-muted-foreground text-sm">
                                {{ user.email }}
                            </div>
                        </TableCell>

                        <TableCell>
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="role in user.roles"
                                    :key="role"
                                    variant="secondary"
                                >
                                    {{ role }}
                                </Badge>
                                <span
                                    v-if="user.roles.length === 0"
                                    class="text-muted-foreground text-sm"
                                >
                                    —
                                </span>
                            </div>
                        </TableCell>

                        <TableCell>
                            <Badge v-if="user.disabled" variant="destructive">
                                Disabled
                            </Badge>
                            <Badge
                                v-else-if="!user.email_verified"
                                variant="outline"
                            >
                                Unverified
                            </Badge>
                            <Badge v-else variant="secondary">Active</Badge>
                        </TableCell>

                        <TableCell class="text-muted-foreground text-sm">
                            {{ formatDate(user.last_login_at) }}
                        </TableCell>

                        <TableCell
                            class="text-muted-foreground text-right text-sm"
                        >
                            {{ formatDate(user.created_at) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination
            :links="users.links"
            :from="users.from"
            :to="users.to"
            :total="users.total"
        />
    </div>
</template>
