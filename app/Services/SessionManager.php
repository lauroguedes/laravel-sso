<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\SessionRevoked;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

/**
 * Reads and revokes the two things that keep a user signed in.
 *
 * A browser session on this server is what lets somebody reach the
 * administration interface and approve authorizations. An access token is what
 * lets an application act on their behalf afterwards. Ending one does not end
 * the other, so an administrator withdrawing access has to be able to see and
 * revoke both.
 */
class SessionManager
{
    /**
     * Browser sessions on this server.
     *
     * Only available when sessions are stored in the database; a file or cache
     * driver keeps no record an administrator could inspect.
     *
     * @return array<int, array{id: mixed, user: array{id: mixed, name: mixed, email: mixed}, ip_address: mixed, user_agent: mixed, last_activity: mixed, current: bool}>
     */
    public function browserSessions(?string $currentId = null): array
    {
        if (! $this->tracksSessions()) {
            return [];
        }

        $rows = DB::table('sessions')
            ->leftJoin('users', 'users.id', '=', 'sessions.user_id')
            ->whereNotNull('sessions.user_id')
            ->orderByDesc('sessions.last_activity')
            ->limit(100)
            ->get([
                'sessions.id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
            ]);

        return $rows->map(fn (object $session): array => [
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
        ])->all();
    }

    /**
     * Access tokens applications currently hold on a user's behalf.
     *
     * Users are loaded separately rather than eager loaded: Passport's
     * Token::user() derives the model from its own client's provider, which
     * is not available when Eloquent builds the relation, so eager loading it
     * fails. Two queries, and no lookup per row.
     *
     * @return array<int, array{id: string, user: array{id: int|null, name: string|null, email: string|null}, application: string|null, scopes: list<string>, expires_at: string|null}>
     */
    public function issuedTokens(): array
    {
        $tokens = $this->liveTokens()
            ->with('client:id,name')
            ->latest('created_at')
            ->limit(100)
            ->get();

        $users = User::query()
            ->whereIn('id', $tokens->pluck('user_id')->filter()->unique())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return $tokens->map(fn (Token $token): array => [
            'id' => $token->id,
            'user' => [
                'id' => $token->user_id,
                'name' => $users[$token->user_id]->name ?? null,
                'email' => $users[$token->user_id]->email ?? null,
            ],
            'application' => $token->client?->name,
            /*
             * Passport casts this to a bare array; normalising here makes the
             * shape the payload promises actually true.
             */
            'scopes' => array_values(array_map(strval(...), $token->scopes ?? [])),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ])->all();
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
     * Counted in the database rather than by measuring the listing, which caps
     * at 100 and would silently report that number for ever after.
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
