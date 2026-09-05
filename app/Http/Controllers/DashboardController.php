<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditLog;
use App\Models\Application;
use App\Models\AuditRecord;
use App\Models\User;
use App\Services\SessionManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The operational overview.
 *
 * Counts and recent activity only — no charts. An administrator opening this
 * wants to know whether anything is wrong, which is a question about recent
 * security events and current state, not about trends.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly SessionManager $sessions) {}

    /**
     * Show the dashboard.
     */
    public function index(Request $request): Response
    {
        $canViewAudit = $request->user()->can('viewAudit', AuditRecord::class);

        return Inertia::render('Dashboard', [
            'counts' => [
                'applications' => Application::query()->where('revoked', false)->count(),
                'users' => User::query()->whereNull('disabled_at')->count(),
                'disabled_users' => User::query()->whereNotNull('disabled_at')->count(),
                'sessions' => $this->sessions->browserSessionCount(),
                'tokens' => $this->sessions->issuedTokenCount(),
            ],
            /*
             * Withheld from anyone without permission to read the audit trail,
             * rather than shown in a weaker form: a summary of security events
             * is still a security event.
             */
            'recent' => $canViewAudit ? [
                'security' => $this->recent(AuditLog::Security),
                'administration' => $this->recent(AuditLog::Administration),
            ] : null,
        ]);
    }

    /**
     * The last few entries from one audit stream.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recent(AuditLog $log): array
    {
        return AuditRecord::query()
            ->inLog($log)
            ->with(['causer:id,name,email', 'application:id,name'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (AuditRecord $record): array => $record->toSummary())
            ->all();
    }
}
