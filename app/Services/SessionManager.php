<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\SessionRevoked;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use stdClass;

/**
 * Reads and revokes the two things that keep a user signed in.
 *
 * A browser session on this server is what lets somebody reach the
 * administration interface and approve authorizations. An access token is what
 * lets an application act on their behalf afterwards. Ending one does not end
 * the other, so an administrator withdrawing access has to be able to see and
 * revoke both.
 *
 * @phpstan-type TokenRow array{id: string, user: array{id: int|null, name: string|null, email: string|null}, application: string|null, scopes: list<string>, expires_at: string|null}
 */
class SessionManager
{
    /**
     * How recently a session was last used, for narrowing the listing, in seconds.
     */
    public const ACTIVITY_WINDOWS = ['hour' => 3_600, 'day' => 86_400, 'week' => 604_800];

    /**
     * Browser sessions on this server, a page at a time, most recently active first.
     *
     * Only available when sessions are stored in the database; a file or cache
     * driver keeps no record an administrator could inspect.
     *
     * @param  'asc'|'desc'|null  $direction  By last activity, newest first when null.
     * @return LengthAwarePaginator<int|string, array{id: mixed, user: array{id: mixed, name: mixed, email: mixed}, ip_address: mixed, user_agent: mixed, last_activity: mixed, current: bool}>
     */
    public function browserSessions(?string $currentId, ?string $search, ?string $active, ?string $direction, int $perPage, string $pageName): LengthAwarePaginator
    {
        if (! $this->tracksSessions()) {
            return new LengthAwarePaginator([], 0, $perPage, 1, ['pageName' => $pageName]);
        }

        $window = $active === null ? null : (self::ACTIVITY_WINDOWS[$active] ?? null);

        return DB::table('sessions')
            ->leftJoin('users', 'users.id', '=', 'sessions.user_id')
            ->whereNotNull('sessions.user_id')
            ->when($search, fn (QueryBuilder $query, string $term) => $query->where(fn (QueryBuilder $query) => $query
                ->whereIn('sessions.user_id', User::query()->search($term)->select('id'))
                ->orWhereLike('sessions.ip_address', "{$term}%")))
            ->when($window, fn (QueryBuilder $query, int $seconds) => $query
                ->where('sessions.last_activity', '>=', now()->subSeconds($seconds)->timestamp))
            ->orderBy('sessions.last_activity', $direction ?? 'desc')
            ->orderBy('sessions.id')
            ->paginate($perPage, [
                'sessions.id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
            ], $pageName)
            ->withQueryString()
            ->through(fn (stdClass $session): array => [
                'id' => $session->id,
                'user' => [
                    'id' => $session->user_id,
                    'name' => $session->user_name,
                    'email' => $session->user_email,
                ],
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => $session->last_activity,
                'current' => $session->id === $currentId,
            ]);
    }

    /**
     * Access tokens applications currently hold on a user's behalf, a page at a time.
     *
     * Users are loaded separately rather than eager loaded: Passport's
     * Token::user() derives the model from its own client's provider, which
     * is not available when Eloquent builds the relation, so eager loading it
     * fails. Two queries per page, and no lookup per row.
     *
     * Each row is a TokenRow. The page is returned as mixed, as
     * AuditController::entries() returns its own: PHPStan will not match a
     * paginator mapped through through() against any declared row type, even an
     * identical one.
     *
     * @param  'asc'|'desc'|null  $direction  By expiry, or newest first when null.
     */
    public function issuedTokens(?string $search, ?string $application, ?string $direction, int $perPage, string $pageName): mixed
    {
        $tokens = $this->liveTokens()
            ->with('client:id,name')
            ->when($search, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->whereIn('user_id', User::query()->search($term)->select('id'))
                ->orWhereIn('client_id', Application::query()->search($term)->select('id'))))
            /*
             * A client id is a UUID column on PostgreSQL, where comparing it with
             * anything else is an error rather than an empty result.
             */
            ->when($application !== null && Str::isUuid($application), fn (Builder $query) => $query->where('client_id', $application))
            ->when(
                $direction,
                fn (Builder $query, string $direction) => $query->orderBy('expires_at', $direction),
                fn (Builder $query) => $query->latest('created_at'),
            )
            ->orderBy('id')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();

        $users = User::query()
            ->whereIn('id', collect($tokens->items())->pluck('user_id')->filter()->unique())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return $tokens->through(fn (Token $token): array => $this->summarizeToken($token, $users));
    }

    /**
     * One row of the token listing.
     *
     * @param  Collection<int, User>  $users  The page's users, by id.
     * @return TokenRow
     */
    private function summarizeToken(Token $token, Collection $users): array
    {
        $user = $users->get($token->user_id);

        return [
            'id' => (string) $token->id,
            'user' => [
                'id' => $token->user_id === null ? null : (int) $token->user_id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'application' => $token->client?->name,
            /*
             * Passport casts this to a bare array; normalising here makes the
             * shape the payload promises actually true.
             */
            'scopes' => array_values(array_map(strval(...), $token->scopes ?? [])),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ];
    }

    /**
     * The applications holding at least one live token, to narrow the listing by.
     *
     * @return list<array{value: string, label: string}>
     */
    public function applicationsHoldingTokens(): array
    {
        return array_values(Application::query()
            ->whereIn('id', $this->liveTokens()->distinct()->select('client_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Application $application): array => [
                'value' => (string) $application->id,
                'label' => (string) $application->name,
            ])
            ->all());
    }

    /**
     * The access tokens that are still usable.
     *
     * @return Builder<Token>
     */
    private function liveTokens(): Builder
    {
        return Passport::token()->newQuery()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->whereNotNull('user_id');
    }

    /**
     * How many browser sessions are open.
     *
     * Counted in the database rather than read off the listing, which only
     * ever holds one page.
     */
    public function browserSessionCount(): int
    {
        return $this->tracksSessions()
            ? DB::table('sessions')->whereNotNull('user_id')->count()
            : 0;
    }

    /**
     * How many access tokens are live.
     */
    public function issuedTokenCount(): int
    {
        return $this->liveTokens()->count();
    }

    /**
     * Determine whether browser sessions can be inspected at all.
     */
    public function tracksSessions(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * End one browser session.
     */
    public function revokeBrowserSession(string $id): void
    {
        $session = DB::table('sessions')->where('id', $id)->first();

        if ($session === null) {
            return;
        }

        DB::table('sessions')->where('id', $id)->delete();

        SessionRevoked::dispatch(
            'browser',
            $session->user_id === null ? null : User::query()->find((int) $session->user_id),
            null,
            ['ip_address' => $session->ip_address],
        );
    }

    /**
     * End a user's other browser sessions, keeping the one they are using.
     *
     * Their tokens are deliberately left alone: this follows a password
     * change, where the point is to evict whoever else knew the old password,
     * not to make the person re-authorize every application they use.
     */
    public function revokeOtherBrowserSessionsFor(User $user, string $keepSessionId): void
    {
        if (! $this->tracksSessions()) {
            return;
        }

        $ended = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $keepSessionId)
            ->delete();

        if ($ended > 0) {
            SessionRevoked::dispatch('others', $user, null, ['sessions' => $ended]);
        }
    }

    /**
     * End every browser session belonging to a user, and revoke their tokens.
     *
     * Both, because leaving either behind would let the user carry on: the
     * session through this server, the tokens through the applications.
     */
    public function revokeEverythingFor(User $user): void
    {
        if ($this->tracksSessions()) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $this->revokeTokensWhere(fn ($query) => $query->where('user_id', $user->id));

        SessionRevoked::dispatch('everything', $user, null, ['user' => $user->email]);
    }

    /**
     * Revoke every token an application holds.
     */
    public function revokeTokensFor(Application $application): void
    {
        $this->revokeTokensWhere(fn ($query) => $query->where('client_id', $application->id));

        SessionRevoked::dispatch('application', null, $application);
    }

    /**
     * Revoke one access token and its refresh token.
     */
    public function revokeToken(string $id): void
    {
        $token = Passport::token()->newQuery()->with('client:id,name')->find($id);

        if ($token === null) {
            return;
        }

        $this->revokeTokensWhere(fn ($query) => $query->where('id', $id));

        /*
         * Both come off the token that was just loaded, rather than being
         * looked up again.
         */
        SessionRevoked::dispatch(
            'token',
            $token->user_id === null ? null : User::query()->find((int) $token->user_id),
            $token->client instanceof Application ? $token->client : null,
        );
    }

    /**
     * Revoke the access tokens a query selects, and their refresh tokens.
     *
     * Two set-based statements regardless of how many tokens match, rather
     * than walking them one at a time as Passport's own ClientRepository does.
     *
     * Public because ApplicationManager revokes an application's tokens when
     * disabling it, and there should be one implementation of the cascade: a
     * future token type has to be added in exactly one place.
     *
     * @param  callable(Builder<Token>): mixed  $constrain
     */
    public function revokeTokensWhere(callable $constrain): void
    {
        $tokens = Passport::token()->newQuery()->where('revoked', false);

        $constrain($tokens);

        Passport::refreshToken()->newQuery()
            ->whereIn('access_token_id', (clone $tokens)->select('id'))
            ->update(['revoked' => true]);

        $tokens->update(['revoked' => true]);
    }
}
