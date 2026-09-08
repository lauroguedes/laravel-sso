<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ApplicationRoleRequest;
use App\Models\Application;
use App\Models\ApplicationPermission;
use App\Models\ApplicationRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the roles one application defines, and the permissions each grants.
 *
 * Roles and permissions here belong to the application, not to this server:
 * they are reported to the application in a token and enforced by it.
 */
class ApplicationRoleController extends Controller
{
    /**
     * Show the roles and permissions an application defines.
     */
    public function index(Request $request, Application $application): Response
    {
        $this->authorize('view', $application);

        return Inertia::render('applications/Roles', [
            'application' => $application->toHeader(),
            'canManageApplication' => $request->user()->can('update', $application),
            'roles' => $application->roles()
                ->with('permissions:id,name')
                ->withCount('grants')
                ->orderBy('name')
                ->get()
                ->map(fn (ApplicationRole $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    /*
                     * Names travel with the ids so the page does not have to
                     * re-join them against the catalogue on every render.
                     */
                    'permissions' => $role->permissions
                        ->map(fn (ApplicationPermission $permission): array => [
                            'id' => $permission->id,
                            'name' => $permission->name,
                        ])
                        ->all(),
                    'users_count' => $role->grants_count,
                ]),
            'permissions' => $application->permissions()
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
            'canManage' => request()->user()->can('update', $application),
        ]);
    }

    /**
     * Define a role.
     */
    public function store(ApplicationRoleRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        DB::transaction(function () use ($request, $application): void {
            $role = $application->roles()->create($request->safe()->only(['name', 'description']));

            $role->permissions()->sync($request->validated('permissions', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return back();
    }

    /**
     * Rename a role or change the permissions it grants.
     */
    public function update(
        ApplicationRoleRequest $request,
        Application $application,
        ApplicationRole $role
    ): RedirectResponse {
        $this->authorize('update', $application);

        DB::transaction(function () use ($request, $role): void {
            $role->update($request->safe()->only(['name', 'description']));

            $role->permissions()->sync($request->validated('permissions', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    /**
     * Remove a role.
     *
     * Users holding it keep their access to the application and simply lose
     * the role, which the nullable foreign key handles.
     */
    public function destroy(Application $application, ApplicationRole $role): RedirectResponse
    {
        $this->authorize('update', $application);

        $role->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role removed. Users who held it keep their access.'),
        ]);

        return back();
    }
}
