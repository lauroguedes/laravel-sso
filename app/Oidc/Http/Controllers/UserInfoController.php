<?php

declare(strict_types=1);

namespace App\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Oidc\Contracts\ResolvesClaims;
use App\Oidc\Exceptions\OAuthError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Passport\Token;

/**
 * Returns the claims of the user an access token was issued for.
 */
class UserInfoController extends Controller
{
    public function __construct(private readonly ResolvesClaims $claims) {}

    /**
     * Handle the incoming request.
     *
     * UserInfo belongs to OpenID Connect, so the token must have been granted
     * openid (OpenID Connect Core section 5.3). A token issued for some other
     * purpose is refused rather than answered.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw OAuthError::invalidToken();

        if (! $user->tokenCan('openid')) {
            throw OAuthError::insufficientScope('openid');
        }

        [$scopes, $clientId] = $this->grantOf($user->currentAccessToken());

        return response()->json($this->claims->claimsFor($user, $scopes, $clientId));
    }

    /**
     * The scopes a token grants, and the client it was issued to.
     *
     * A bearer token resolves to an AccessToken, which carries both among its
     * attributes. Asking it for "scopes" or "client_id" instead is forwarded
     * to the token's row and costs a query. The cookie guard yields that row,
     * a Token, itself.
     *
     * @return array{0: array<int, string>, 1: string|null}
     */
    private function grantOf(?ScopeAuthorizable $token): array
    {
        return match (true) {
            $token instanceof AccessToken => [$token->oauth_scopes, $token->oauth_client_id],
            $token instanceof Token => [$token->scopes, $token->client_id],
            default => [['openid'], null],
        };
    }
}
