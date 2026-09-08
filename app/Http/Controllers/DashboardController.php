<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditLog;
use App\Enums\PlatformPermission;
use App\Models\Application;
use App\Models\ApplicationUser;
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
        $user = $request->user();

        /*
         * Someone with no administrative permission sees their own account
         * instead of the server's. The operator view counts every user and
         * lists recent security events, neither of which is theirs to read,
         * and withholding the panels one by one would leave a page that is
         * mostly absence.
         */
        /*
         * Asked of the platform permission rather than the policy: somebody
         * who stewards an application can view that application, but the
         * counts below are of every user and every application on the server,
         * which is not the same question.
         */
        if (! $user->can('viewAny', User::class) && ! $user->can(PlatformPermission::ApplicationsView->value)) {
            return $this->personal($user);
        }

        $canViewAudit = $user->can('viewAudit', AuditRecord::class);

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
     * What one user can see about their own account.
     *
     * Read only, and scoped to them: the applications they may sign in to,
     * what they hold in each, and when they last signed in. Nothing here
     * describes anybody else.
     */
    private function personal(User $user): Response
    {
        return Inertia::render('dashboard/Personal', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            ],
            'access' => $user->applicationGrants()
                ->with([
                    'application:id,name,description,revoked,grant_types,secret',
                    'role:id,name',
                    'role.permissions:id,name',
                ])
                ->get()
                ->sortBy(fn (ApplicationUser $grant): string => $grant->application->name)
                ->values()
                ->map(fn (ApplicationUser $grant): array => [
                    'application' => $grant->application->name,
                    'description' => $grant->application->description,
                    'enabled' => $grant->application->isEnabled(),
                    'role' => $grant->role?->name,
                    'permissions' => $grant->role
                        ?->permissions
                        ->pluck('name')
                        ->all() ?? [],
                ])
                ->all(),
            /*
             * The applications this person looks after, as opposed to the ones
             * they may sign in to above. A developer's own way in to the work
             * they were assigned.
             */
            'stewardship' => $user->can(PlatformPermission::ApplicationsDevelop->value)
                ? $user->managedApplications()
                    ->orderBy('name')
                    ->get(['oauth_clients.id', 'name', 'description', 'revoked'])
                    ->map(fn (Application $application): array => [
                        'id' => $application->id,
                        'name' => $application->name,
                        'description' => $application->description,
                        'enabled' => $application->isEnabled(),
                    ])
                    ->all()
                : [],
            /*
             * Platform roles govern this administration interface. Someone
             * reaching this page holds none that open a section on their own,
             * but naming what they do hold is more useful than an empty panel.
             */
            'platformRoles' => $user->getRoleNames()->all(),
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
