<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ApplicationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Takes an application out of service and returns it.
 *
 * Disabling revokes the application's tokens as well as blocking new ones,
 * because an access token already in flight would otherwise keep working until
 * it expired.
 */
class ApplicationStatusController extends Controller
{
    public function __construct(private readonly ApplicationManager $applications) {}

    /**
     * Enable or disable an application.
     */
    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $request->validate(['enabled' => ['required', 'boolean']]);

        if ($request->boolean('enabled')) {
            $this->applications->enable($application);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Application enabled.')]);
        } else {
            $this->applications->disable($application);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Application disabled and its tokens revoked.'),
            ]);
        }

        return back();
    }
}
