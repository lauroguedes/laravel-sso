<?php

declare(strict_types=1);

namespace App\Services;

use Admin9\OidcServer\Contracts\OidcUserInterface;
use Admin9\OidcServer\Services\ClaimsService;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use Throwable;

/**
 * Adds application scoped authorization claims to ID Tokens and UserInfo.
 *
 * The package's ClaimsService resolves claims from the user and the granted
 * scopes alone, with no notion of which application asked. That is fine for
 * "name" or "email", which are the same whoever is asking, but roles and
 * permissions are not: reporting must never learn what a user may do in
 * billing.
 *
 * The requesting client is therefore established here, from one of two places:
 *
 *  - during token issuance, ApplicationIdTokenService wraps generation in
 *    forClient(), because only it knows the client at that point;
 *  - at the UserInfo endpoint, from the access token presented by the caller.
 *
 * If neither yields a client, the authorization claims are omitted rather than
 * guessed.
 */
class ApplicationClaimsService extends ClaimsService
{
    /**
     * The claims this service knows how to compute.
     *
     * Which of them a token actually carries is decided by the scopes the
     * application was granted, through "config/oidc-server.php".
     */
    private const COMPUTABLE_CLAIMS = ['roles', 'permissions'];

    /**
     * The application currently being issued a token, if known.
     */
    private ?string $clientId = null;

    /**
     * Resolve claims for a user, adding those scoped to the requesting client.
     *
     * @param  array<string>  $scopes
     * @return array<string, mixed>
     */
    public function resolveForUser(OidcUserInterface $user, array $scopes): array
    {
        $claims = parent::resolveForUser($user, $scopes);

        $requested = $this->authorizationClaimsGrantedBy($scopes);

        if ($requested === [] || ! $user instanceof User) {
            return $claims;
        }

        $clientId = $this->clientId ?? $this->clientIdFromAccessToken($user);

        if ($clientId === null) {
            return $claims;
        }

        $authorization = $this->authorizationClaims($user, $clientId);

        return [...$claims, ...array_intersect_key($authorization, array_flip($requested))];
    }

    /**
     * Resolve claims for the given client for the duration of the callback.
     *
     * The client is always restored afterwards so that a long-lived worker
     * cannot carry one request's client into the next.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function forClient(string $clientId, Closure $callback): mixed
    {
        $previous = $this->clientId;

        $this->clientId = $clientId;

        try {
            return $callback();
        } finally {
            $this->clientId = $previous;
        }
    }

    /**
     * Which of the claims this service can compute were granted by the scopes.
     *
     * Read from "config/oidc-server.php" rather than matched against a scope
     * name, so the configuration that discovery advertises is the same one
     * that decides what a token carries.
     *
     * @param  array<string>  $scopes
     * @return array<int, string>
     */
    private function authorizationClaimsGrantedBy(array $scopes): array
    {
        /** @var array<string, array{claims?: array<int, string>}> $configured */
        $configured = config('oidc-server.scopes', []);

        $granted = [];

        foreach ($scopes as $scope) {
            $granted = [...$granted, ...($configured[$scope]['claims'] ?? [])];
        }

        return array_values(array_intersect(self::COMPUTABLE_CLAIMS, $granted));
    }

    /**
     * The role and permissions the user holds in the requesting application.
     *
     * A user with no access grant, or a grant with no role, reports empty
     * arrays rather than being omitted: the application can then tell "signed
     * in with nothing granted" apart from "authorization was not requested".
     *
     * This runs on every token issuance and every UserInfo request, so it is
     * one flat join returning strings rather than an Eloquent read that would
     * hydrate three models across three round trips.
     *
     * @return array{roles: array<int, string>, permissions: array<int, string>}
     */
    private function authorizationClaims(User $user, string $clientId): array
    {
        $rows = DB::table('application_user')
            ->leftJoin('application_roles', 'application_roles.id', '=', 'application_user.application_role_id')
            ->leftJoin('application_permission_role', 'application_permission_role.application_role_id', '=', 'application_roles.id')
            ->leftJoin('application_permissions', 'application_permissions.id', '=', 'application_permission_role.application_permission_id')
            ->where('application_user.application_id', $clientId)
            ->where('application_user.user_id', $user->id)
            ->get(['application_roles.name as role_name', 'application_permissions.name as permission_name']);

        $roleName = $rows->first()?->role_name;

        return [
            'roles' => $roleName === null ? [] : [$roleName],
            'permissions' => $rows
                ->pluck('permission_name')
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * The client behind the access token the caller presented, if any.
     *
     * Used by the UserInfo endpoint, which authenticates with a bearer token
     * rather than issuing one.
     *
     * A bearer token resolves to an AccessToken, which carries the client on
     * the request attributes; reading "client_id" from it instead would be
     * forwarded to the underlying model and cost a query. The cookie guard
     * yields the Token model itself.
     */
    private function clientIdFromAccessToken(User $user): ?string
    {
        try {
            $token = $user->token();
        } catch (Throwable) {
            return null;
        }

        return match (true) {
            $token instanceof AccessToken => $token->oauth_client_id,
            $token instanceof Token => $token->client_id,
            default => null,
        };
    }
}
