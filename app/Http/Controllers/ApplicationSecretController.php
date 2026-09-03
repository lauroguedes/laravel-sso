<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ApplicationManager;
use Illuminate\Http\RedirectResponse;

/**
 * Rotates an application's client secret.
 */
class ApplicationSecretController extends Controller
{
    public function __construct(private readonly ApplicationManager $applications) {}

    /**
     * Issue a new client secret, invalidating the previous one immediately.
     *
     * The new secret travels to the next request in the session rather than in
     * the URL, and is shown exactly once.
     */
    public function update(Application $application): RedirectResponse
    {
        $this->authorize('regenerateSecret', $application);

        $secret = $this->applications->regenerateSecret($application);

        return to_route('applications.show', $application)->with('clientSecret', $secret);
    }
}
