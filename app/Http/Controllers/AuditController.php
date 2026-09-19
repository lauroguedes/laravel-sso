<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\DescribesApplicationSections;
use App\Concerns\SortsListings;
use App\Enums\AuditLog;
use App\Models\Application;
use App\Models\AuditRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shows the audit trail.
 *
 * Read only, deliberately: nothing in this application edits or deletes an
 * entry. Retention is the "activitylog:clean" command's job.
 */
class AuditController extends Controller
{
    use DescribesApplicationSections;
    use SortsListings;

    /**
     * List audit entries, newest first.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAudit', AuditRecord::class);

        $search = $request->string('search')->toString() ?: null;
        $stream = AuditLog::tryFrom($request->string('stream')->toString());

        return Inertia::render('audit/Index', [
            'filters' => [
                'search' => $search,
                'stream' => $stream?->value,
                ...$this->sortFilters($request, AuditRecord::sortableColumns()),
            ],
            'streams' => array_map(fn (AuditLog $log): array => [
                'value' => $log->value,
                'label' => $log->label(),
            ], AuditLog::cases()),
            'entries' => $this->entries(
                AuditRecord::query()->when($stream, fn ($query) => $query->inLog($stream)),
                $search,
                $this->perPage($request),
                $this->sortColumn($request, AuditRecord::sortableColumns()),
                $this->sortDirection($request),
            ),
        ]);
    }

    /**
     * List the audit entries concerning one application.
     */
    public function forApplication(Request $request, Application $application): Response
    {
        /*
         * Both: the page names the application, so the reader has to be
         * allowed to see it, and separately allowed to read its history.
         */
        $this->authorize('view', $application);
        $this->authorize('viewForApplication', [AuditRecord::class, $application]);

        $search = $request->string('search')->toString() ?: null;

        return Inertia::render('applications/Audit', [
            'application' => $application->toHeader(),
            'sections' => $this->applicationSections($request->user(), $application),
            'canManageApplication' => $request->user()->can('update', $application),
            'filters' => [
                'search' => $search,
                ...$this->sortFilters($request, AuditRecord::sortableColumns()),
            ],
            'entries' => $this->entries(
                AuditRecord::query()->forApplication($application),
                $search,
                $this->perPage($request),
                $this->sortColumn($request, AuditRecord::sortableColumns()),
                $this->sortDirection($request),
                /*
                 * A steward reads this to understand what happened to their
                 * application. Who did it, from which address, is a roll of
                 * administrators — the same reason the access list is withheld
                 * from them — so it is left out unless they may read the trail
                 * in its own right.
                 */
                identified: $request->user()->can('viewAudit', AuditRecord::class),
            ),
        ]);
    }

    /**
     * The shape of a paginated listing.
     *
     * "identified" carries who acted and from where. Withheld from a reader
     * who may see one application's history but not the trail itself: what
     * happened to their application is theirs to know, the administrators who
     * did it are not.
     *
     * @param  Builder<AuditRecord>  $query
     * @param  'asc'|'desc'  $direction
     */
    private function entries(
        $query,
        ?string $search,
        int $perPage,
        ?string $sort = null,
        string $direction = 'desc',
        bool $identified = true,
    ): mixed {
        return $query
            ->search($search)
            ->with(['causer:id,name,email', 'application:id,name'])
            /*
             * Newest first unless asked otherwise, and always tie-broken by
             * id: two entries written in the same second must still come back
             * in a stable order between pages.
             */
            ->when(
                $sort === null,
                fn ($query) => $query->latest('id'),
                fn ($query) => $query->orderBy($sort, $direction)->latest('id'),
            )
            /*
             * Counted, as every listing here is, so its pages can be offered
             * by number. This is the fastest growing table in the schema, and
             * the count is what the retention window bounds: keep
             * "activitylog:clean" scheduled.
             */
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditRecord $record): array => $identified
                ? $record->toSummary()
                : [...$record->toSummary(), 'causer' => null, 'ip_address' => null]);
    }
}
