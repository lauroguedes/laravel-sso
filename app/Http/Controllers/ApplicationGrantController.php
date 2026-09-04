<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ApplicationGrantRequest;
use App\Models\Application;
use App\Models\ApplicationUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages which users may sign in to an application, and the role they hold.
 *
 * Access and role are separate: a user may be granted access with no role,
 * which authenticates them without granting any capability.
 *
 * The grant, role change and revoke events are raised by the models, so every
 * caller emits them, not just this controller.
 */
class ApplicationGrantController extends Controller
{
    /**
     * Show the users with access to an application.
     */
    public function index(Request $request, Application $application): Response
    {
        $this->authorize('view', $application);

        $search = $request->string('search')->toString() ?: null;

        return Inertia::render('applications/Access', [
            'application' => [
                'id' => $application->id,
                'name' => $application->name,
            ],
            'filters' => ['search' => $search],
            /*
             * Sorted and paginated in SQL. The name lives on the joined user,
             * so ordering in PHP would have meant loading every grant.
             */
            'grants' => $application->grants()
                ->with('user:id,name,email,disabled_at')
                ->join('users', 'users.id', '=', 'application_user.user_id')
                ->orderBy('users.name')
                ->paginate(15, ['application_user.*'])
                ->withQueryString()
                ->through(fn (ApplicationUser $grant): array => [
                    'id' => $grant->id,
                    'user' => [
                        'id' => $grant->user->id,
                        'name' => $grant->user->name,
                        'email' => $grant->user->email,
                        'disabled' => $grant->user->isDisabled(),
                    ],
                    'role_id' => $grant->application_role_id,
                ]),
            /*
             * Only users who do not already have access, so the picker cannot
             * offer a choice that validation would then reject.
             */
            'candidates' => User::query()
                ->search($search)
                ->whereDoesntHave('applicationGrants', fn ($query) => $query
                    ->where('application_id', $application->id))
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'email']),
            'roles' => $application->roles()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('manageAccess', $application),
        ]);
    }

    /**
     * Grant a user access to the application.
     */
    public function store(ApplicationGrantRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('manageAccess', $application);

        $application->grantAccessTo(
            User::query()->findOrFail((int) $request->validated('user_id')),
            $request->applicationRole(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access granted.')]);

        return back();
    }

    /**
     * Change the role a user holds in the application.
     */
    public function update(
        ApplicationGrantRequest $request,
        Application $application,
        ApplicationUser $grant
    ): RedirectResponse {
        $this->authorize('manageAccess', $application);

        $grant->assignRole($request->applicationRole());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    /**
     * Withdraw a user's access to the application.
     */
    public function destroy(Application $application, ApplicationUser $grant): RedirectResponse
    {
        $this->authorize('manageAccess', $application);

        $grant->revoke();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access revoked.')]);

        return back();
    }
}
