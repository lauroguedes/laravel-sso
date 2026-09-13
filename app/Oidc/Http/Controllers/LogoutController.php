<?php

declare(strict_types=1);

namespace App\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Oidc\Contracts\EndsSessions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the user's session when a relying party signs them out.
 */
class LogoutController extends Controller
{
    public function __construct(private readonly EndsSessions $sessions) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        return $this->sessions->endSession($request);
    }
}
