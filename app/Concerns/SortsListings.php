<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Services\InterfaceOptions;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Applies a sort chosen in the interface to a listing query.
 *
 * Sorting happens in the database rather than in the browser because every
 * listing is paginated: ordering the current page would reorder fifteen rows
 * while appearing to have ordered the whole table.
 *
 * The column is matched against an allowlist the caller supplies. A request
 * parameter otherwise reaches straight into an ORDER BY clause, where an
 * unexpected column leaks the existence and ordering of data the listing was
 * never meant to expose.
 */
trait SortsListings
{
    /**
     * Order the query by the requested column, or leave the default in place.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $sortable
     * @return Builder<TModel>
     */
    protected function applySort(Builder $query, Request $request, array $sortable): Builder
    {
        $column = $this->sortColumn($request, $sortable);

        if ($column === null) {
            return $query;
        }

        return $query->orderBy($column, $this->sortDirection($request));
    }

    /**
     * The requested column, when it is one this listing offers.
     *
     * @param  array<int, string>  $sortable
     */
    protected function sortColumn(Request $request, array $sortable): ?string
    {
        $column = $request->string('sort')->toString();

        return in_array($column, $sortable, true) ? $column : null;
    }

    /**
     * The requested direction, defaulting to ascending.
     *
     * @return 'asc'|'desc'
     */
    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }

    /**
     * How many rows a listing should show.
     *
     * Chosen in the interface and carried in the URL, so a page size survives
     * a search, a sort and the back button. Constrained to a short list of
     * offered sizes because the value reaches a LIMIT clause: an arbitrary
     * number is an invitation to ask for a million rows.
     *
     * @return int<1, max>
     */
    protected function perPage(Request $request): int
    {
        $requested = $request->integer('per_page');

        if (in_array($requested, InterfaceOptions::PAGE_SIZES, true)) {
            return $requested;
        }

        /*
         * The operator's default, which they set once for everyone; a reader
         * overrides it for themselves at the foot of any table.
         */
        $configured = app(Settings::class)->get('rows_per_page');

        return in_array($configured, InterfaceOptions::PAGE_SIZES, true)
            ? $configured
            : InterfaceOptions::PAGE_SIZES[0];
    }

    /**
     * One listing's parameters, with its prefix taken off.
     *
     * A page holding more than one listing gives each a prefix, so their
     * searches, filters, sorts, sizes and pages cannot collide. Stripping it
     * lets the methods above read them as they read a single listing's.
     */
    protected function listing(Request $request, string $prefix): Request
    {
        return new Request(collect($request->query())
            ->filter(fn (mixed $value, int|string $key): bool => str_starts_with((string) $key, $prefix))
            ->mapWithKeys(fn (mixed $value, int|string $key): array => [substr((string) $key, strlen($prefix)) => $value])
            ->all());
    }

    /**
     * The direction a listing was asked to sort in, or null for its own default.
     *
     * @param  array<int, string>  $sortable
     * @return 'asc'|'desc'|null
     */
    protected function sortedDirection(Request $request, array $sortable): ?string
    {
        return $this->sortColumn($request, $sortable) === null ? null : $this->sortDirection($request);
    }

    /**
     * Everything one listing echoes back to the interface: what it was narrowed
     * by, how it is ordered, and how many rows it shows.
     *
     * @param  array<int, string>  $sortable
     * @return array{search: string|null, sort: string|null, direction: string|null, per_page: int}
     */
    protected function listingFilters(Request $request, array $sortable = []): array
    {
        return [
            'search' => $request->string('search')->toString() ?: null,
            ...$this->sortFilters($request, $sortable),
        ];
    }

    /**
     * The sort to echo back to the interface, so the header shows its arrow,
     * with the page size beside it.
     *
     * @param  array<int, string>  $sortable
     * @return array{sort: string|null, direction: string|null, per_page: int}
     */
    protected function sortFilters(Request $request, array $sortable): array
    {
        $column = $this->sortColumn($request, $sortable);

        return [
            'sort' => $column,
            'direction' => $column === null ? null : $this->sortDirection($request),
            'per_page' => $this->perPage($request),
        ];
    }
}
