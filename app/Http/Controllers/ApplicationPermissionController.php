<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreApplicationPermissionRequest;
use App\Models\Application;
use App\Models\ApplicationPermission;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Maintains the catalogue of capabilities one application recognises.
 *
 * Permissions are grouped by roles and reported to the application in a token.
 * This server never enforces them.
 */
class ApplicationPermissionController extends Controller
{
    /**
     * Add a permission to the application's catalogue.
     */
    public function store(StoreApplicationPermissionRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $application->permissions()->create($request->safe()->only(['name', 'description']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission added.')]);

        return back();
    }

    /**
     * Remove a permission, detaching it from every role that granted it.
     */
    public function destroy(Application $application, ApplicationPermission $permission): RedirectResponse
    {
        $this->authorize('update', $application);

        $permission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission removed.')]);

        return back();
    }
}
