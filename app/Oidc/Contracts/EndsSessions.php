<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the user's session at a relying party's request.
 *
 * @see https://openid.net/specs/openid-connect-rpinitiated-1_0.html
 */
interface EndsSessions
{
    /**
     * Handle a logout request and decide where the browser goes next.
     */
    public function endSession(Request $request): Response;
}
