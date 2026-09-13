<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

use Illuminate\Http\Request;

/**
 * Checks a relying party's request to end the user's session.
 *
 * @see https://openid.net/specs/openid-connect-rpinitiated-1_0.html
 */
interface EndsSessions
{
    /**
     * Which client the request comes from, where the browser may go
     * afterwards, and which user an id_token_hint this server signed names.
     */
    public function inspect(Request $request): LogoutRequest;
}
