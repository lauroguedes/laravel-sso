<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Application;
use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a signed-in user is not permitted to use an application.
 *
 * Rendering lives on the exception rather than at the place that detects it,
 * so every route into an authorization — the initial request, the approval —
 * refuses the same way, with the same status.
 *
 * The user is told here rather than redirected back to the application with an
 * "access_denied" error, because producing that redirect would mean treating
 * the redirect URI as trustworthy before the OAuth2 server has validated it.
 */
class ApplicationAccessDenied extends Exception
{
    public function __construct(private readonly Application $application)
    {
        parent::__construct("The user is not permitted to use {$application->name}.");
    }

    /**
     * Render the refusal.
     */
    public function render(Request $request): Response
    {
        return Inertia::render('oauth/AccessDenied', [
            'application' => ['name' => $this->application->name],
        ])->toResponse($request)->setStatusCode(Response::HTTP_FORBIDDEN);
    }
}
