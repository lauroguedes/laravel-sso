<?php

declare(strict_types=1);

namespace App\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Oidc\Contracts\AuthenticatesClients;
use App\Oidc\Contracts\RevokesTokens;
use App\Oidc\Exceptions\OAuthError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets a client give up a token it holds.
 */
class RevocationController extends Controller
{
    public function __construct(
        private readonly AuthenticatesClients $clients,
        private readonly RevokesTokens $tokens,
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $client = $this->clients->authenticate($request) ?? throw OAuthError::invalidClient();

        $this->tokens->revoke(
            $client,
            $request->string('token')->toString() ?: null,
            $request->string('token_type_hint')->toString() ?: null,
        );

        return response()->json([]);
    }
}
