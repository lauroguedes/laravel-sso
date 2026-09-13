<?php

declare(strict_types=1);

namespace App\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Oidc\Contracts\AuthenticatesClients;
use App\Oidc\Contracts\IntrospectsTokens;
use App\Oidc\Exceptions\OAuthError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tells a client whether a token is still active, and what it grants.
 */
class IntrospectionController extends Controller
{
    public function __construct(
        private readonly AuthenticatesClients $clients,
        private readonly IntrospectsTokens $tokens,
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $client = $this->clients->authenticate($request) ?? throw OAuthError::invalidClient();

        return response()->json($this->tokens->introspect(
            $client,
            $request->string('token')->toString() ?: null,
            $request->string('token_type_hint')->toString() ?: null,
        ));
    }
}
