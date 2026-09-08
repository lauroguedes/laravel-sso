<script setup lang="ts" generic="TRow extends Record<string, unknown>">
import {
    ArrowDown,
    ArrowUp,
    ChevronsUpDown,
    SlidersHorizontal,
} from '@lucide/vue';
import IconButton from '@/components/IconButton.vue';
import {
    getCoreRowModel,
    useVueTable,
    type Column,
    type ColumnDef,
    type SortingState,
    type VisibilityState,
} from '@tanstack/vue-table';
import { computed, ref } from 'vue';
import type { DataTableColumn, DataTableSort } from '@/types/administration';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/**
 * A list of records, with sortable columns and a column picker.
 *
 * TanStack Table owns the column and row models, as shadcn-vue's data table
 * does, so filtering, grouping or faceting are a configuration line away
 * rather than a rewrite. Cells render through a "cell-<id>" slot rather than a
 * render function, so a column can still hold a badge or a link without
 * leaving the template.
 *
 * Sorting is handed back to the caller rather than done here. Every list on
 * this server is paginated, and sorting a page in the browser would reorder
 * fifteen rows while claiming to have ordered the whole table.
 */
const {
    columns,
    rows,
    sort = null,
    empty = 'Nothing to show.',
    rowKey,
} = defineProps<{
    columns: DataTableColumn[];
    rows: TRow[];
    sort?: DataTableSort;
    empty?: string;
    rowKey: (row: TRow) => string | number;
}>();

const emit = defineEmits<{ 'update:sort': [DataTableSort] }>();

const visibility = ref<VisibilityState>({});

/*
 * Mirrored into the table so a header can ask the column whether it is sorted,
 * rather than this component tracking that a second time. Sorting itself is
 * the server's, hence manualSorting.
 */
const sorting = computed<SortingState>(() =>
    sort === null ? [] : [{ id: sort.column, desc: sort.direction === 'desc' }],
);

const definitions = computed<ColumnDef<TRow>[]>(() =>
    columns.map((column) => ({
        id: column.id,
        accessorKey: column.id,
        header: column.header,
        enableSorting: column.sortable === true,
        enableHiding: column.alwaysVisible !== true,
        meta: { align: column.align ?? 'left' },
    })),
);

const table = useVueTable({
    get data() {
        return rows;
    },
    get columns() {
        return definitions.value;
    },
    state: {
        get sorting() {
            return sorting.value;
        },
        get columnVisibility() {
            return visibility.value;
        },
    },
    manualSorting: true,
    enableSortingRemoval: false,
    onColumnVisibilityChange: (updater) => {
        visibility.value =
            typeof updater === 'function' ? updater(visibility.value) : updater;
    },
    getCoreRowModel: getCoreRowModel(),
});

const headers = computed(() => table.getHeaderGroups()[0]?.headers ?? []);

const hideableColumns = computed(() =>
    table.getAllColumns().filter((column) => column.getCanHide()),
);

function alignment(column: Column<TRow, unknown>): string {
    return column.columnDef.meta?.align === 'right' ? 'text-right' : '';
}

/**
 * Move a column between ascending and descending.
 */
function toggleSort(column: Column<TRow, unknown>) {
    if (!column.getCanSort()) {
        return;
    }

    emit('update:sort', {
        column: column.id,
        direction:
            sort?.column === column.id && sort.direction === 'asc'
                ? 'desc'
                : 'asc',
    });
}

function sortIcon(column: Column<TRow, unknown>) {
    if (sort?.column !== column.id) {
        return ChevronsUpDown;
    }

    return sort.direction === 'asc' ? ArrowUp : ArrowDown;
}
</script>

<template>
    <div class="space-y-3">
        <!--
            The controls that narrow the list sit with it rather than above the
            page: a search box a card away from its results reads as a page
            filter, not a table one.
        -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <slot name="toolbar" />
            </div>

            <div class="flex items-center gap-2">
                <slot name="filters" />

                <DropdownMenu v-if="hideableColumns.length > 0">
                    <DropdownMenuTrigger as-child>
                        <span>
                            <IconButton
                                label="Columns"
                                :icon="SlidersHorizontal"
                                variant="outline"
                            />
                        </span>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Shown columns</DropdownMenuLabel>
                        <DropdownMenuSeparator />

                        <DropdownMenuCheckboxItem
                            v-for="column in hideableColumns"
                            :key="column.id"
                            :model-value="column.getIsVisible()"
                            @update:model-value="
                                (value: boolean) =>
                                    column.toggleVisibility(value)
                            "
                        >
                            {{ column.columnDef.header }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="header in headers"
                            :key="header.id"
                            :class="alignment(header.column)"
                        >
                            <button
                                v-if="header.column.getCanSort()"
                                type="button"
                                class="hover:text-foreground inline-flex items-center gap-1.5 transition-colors"
                                :aria-label="`Sort by ${header.column.columnDef.header}`"
                                @click="toggleSort(header.column)"
                            >
                                {{ header.column.columnDef.header }}
                                <component
                                    :is="sortIcon(header.column)"
                                    class="size-3.5"
                                    :class="
                                        sort?.column === header.column.id
                                            ? 'text-foreground'
                                            : 'text-muted-foreground/60'
                                    "
                                />
                            </button>

                            <template v-else>
                                {{ header.column.columnDef.header }}
                            </template>
                        </TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableEmpty
                        v-if="table.getRowModel().rows.length === 0"
                        :colspan="headers.length"
                    >
                        {{ empty }}
                    </TableEmpty>

                    <TableRow
                        v-for="row in table.getRowModel().rows"
                        :key="rowKey(row.original)"
                    >
                        <TableCell
                            v-for="cell in row.getVisibleCells()"
                            :key="cell.id"
                            :class="alignment(cell.column)"
                        >
                            <slot
                                :name="`cell-${cell.column.id}`"
                                :row="row.original"
                            >
                                {{ cell.getValue() }}
                            </slot>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!--
            The pager renders here rather than as a sibling of this component,
            so it sits against the table it pages instead of a page-level gap
            away from it.
        -->
        <slot name="footer" />
    </div>
</template>
