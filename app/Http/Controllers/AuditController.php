<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
            ],
            'streams' => array_map(fn (AuditLog $log): array => [
                'value' => $log->value,
                'label' => $log->label(),
            ], AuditLog::cases()),
            'entries' => $this->entries(
                AuditRecord::query()->when($stream, fn ($query) => $query->inLog($stream)),
                $search,
            ),
        ]);
    }

    /**
     * List the audit entries concerning one application.
     */
    public function forApplication(Request $request, Application $application): Response
    {
        $this->authorize('view', $application);
        $this->authorize('viewAudit', AuditRecord::class);

        $search = $request->string('search')->toString() ?: null;

        return Inertia::render('applications/Audit', [
            'application' => $application->toHeader(),
            'canManageApplication' => $request->user()->can('update', $application),
            'filters' => ['search' => $search],
            'entries' => $this->entries(
                AuditRecord::query()->forApplication($application),
                $search,
            ),
        ]);
    }

    /**
     * The shape of a paginated listing.
     *
     * @param  Builder<AuditRecord>  $query
     */
    private function entries($query, ?string $search): mixed
    {
        return $query
            ->search($search)
            ->with(['causer:id,name,email', 'application:id,name'])
            ->latest('id')
            /*
             * Simple pagination on purpose: a numbered pager would run a
             * count over the whole table on every view, and this is the one
             * table that grows with every sign-in and every failed sign-in.
             */
            ->simplePaginate(25)
            ->withQueryString()
            ->through(fn (AuditRecord $record): array => $record->toSummary());
    }
}
