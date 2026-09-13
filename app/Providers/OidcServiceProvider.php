<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Application;
use App\Oidc\Claims;
use App\Oidc\ClientAuthentication;
use App\Oidc\ConsentScreen;
use App\Oidc\Contracts\AuthenticatesClients;
use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\EndsSessions;
use App\Oidc\Contracts\IntrospectsTokens;
use App\Oidc\Contracts\IssuesIdTokens;
use App\Oidc\Contracts\ProvidesSigningKeys;
use App\Oidc\Contracts\ResolvesClaims;
use App\Oidc\Contracts\RevokesTokens;
use App\Oidc\Discovery;
use App\Oidc\Http\Controllers\AuthorizationController;
use App\Oidc\IdTokens;
use App\Oidc\Passport\AccessTokenRepository;
use App\Oidc\Passport\AuthCodeRepository;
use App\Oidc\Passport\AuthorizationContext;
use App\Oidc\Passport\ClientRepository;
use App\Oidc\Passport\IdTokenResponse;
use App\Oidc\Sessions;
use App\Oidc\SigningKeys;
use App\Oidc\TokenState;
use App\Services\ScopeRegistry;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use Laravel\Passport\ClientRepository as PassportClientRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wires the OpenID Connect layer into Passport.
 *
 * Passport is the OAuth 2.0 server. What OpenID Connect adds on top of it lives
 * in app/Oidc, behind the interfaces in App\Oidc\Contracts, and each interface
 * is bound to its implementation here: a different implementation is a
 * different binding. Passport itself is configured from "config/oidc.php".
 *
 * Two Passport features are deliberately left untouched:
 *
 * - The Implicit and Resource Owner Password Credentials grants are disabled
 *   by default. Passport::enableImplicitGrant() and enablePasswordGrant()
 *   must never be called, and discovery advertises neither grant.
 * - Client secrets are hashed by Passport's Client model, and the plain text
 *   value only exists on the request that generated it. That is what allows
 *   the administration interface to show a secret exactly once.
 */
class OidcServiceProvider extends ServiceProvider
{
    /**
     * Each interface, and the class that implements it.
     *
     * Built when something first asks for one, never while the application
     * boots: several read the signing keys or the application key, which a
     * server being installed does not have yet.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DiscoversProvider::class => Discovery::class,
        ProvidesSigningKeys::class => SigningKeys::class,
        AuthenticatesClients::class => ClientAuthentication::class,
        IntrospectsTokens::class => TokenState::class,
        RevokesTokens::class => TokenState::class,
        ResolvesClaims::class => Claims::class,
        IssuesIdTokens::class => IdTokens::class,
        EndsSessions::class => Sessions::class,
        PassportClientRepository::class => ClientRepository::class,
        PassportAuthCodeRepository::class => AuthCodeRepository::class,
        PassportAccessTokenRepository::class => AccessTokenRepository::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AuthorizationContext::class);

        /*
         * Every protocol endpoint, Passport's authorize and token controllers
         * included, is declared in "routes/oidc.php". Passport registers its
         * own routes while it boots, so this has to be said before then.
         */
        Passport::ignoreRoutes();
        Passport::useClientModel(Application::class);

        /*
         * Passport reads token lifetimes only while it builds its authorization
         * server, inside the singleton itself, so they are handed over just
         * before that happens rather than frozen at boot.
         */
        $this->app->beforeResolving(AuthorizationServer::class, fn () => $this->applyTokenLifetimes());

        /*
         * Passport gives its own authorization controller the session guard
         * this way, and a subclass is a different class to the container.
         */
        $this->app->when(AuthorizationController::class)
            ->needs(StatefulGuard::class)
            ->give(fn () => Auth::guard(config('passport.guard')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();
        $this->recordAuthorizationContext();
        $this->configureRateLimiting();
    }

    /**
     * Passport's scopes, consent page and token response.
     */
    private function configurePassport(): void
    {
        Passport::tokensCan(array_column($this->app->make(ScopeRegistry::class)->all(), 'description', 'id'));
        Passport::defaultScopes((array) config('oidc.default_scopes'));

        Passport::useAuthorizationServerResponseType(new IdTokenResponse);

        Passport::authorizationView(fn (array $parameters): Response => $this->app->make(ConsentScreen::class)->render(
            $parameters['client'],
            $parameters['scopes'],
            $parameters['authToken'],
            $parameters['request'],
        ));
    }

    /**
     * The lifetimes in "oidc.tokens", where SettingsServiceProvider puts the
     * ones an administrator chose.
     */
    private function applyTokenLifetimes(): void
    {
        Passport::tokensExpireIn(CarbonInterval::seconds((int) config('oidc.tokens.access_token_ttl')));
        Passport::refreshTokensExpireIn(CarbonInterval::seconds((int) config('oidc.tokens.refresh_token_ttl')));
        Passport::personalAccessTokensExpireIn(CarbonInterval::seconds((int) config('oidc.tokens.access_token_ttl')));
    }

    /**
     * Every authorization code and access token records whatever the current
     * request holds for it, in the insert Passport already makes, and takes it
     * so it cannot reach a second one.
     */
    private function recordAuthorizationContext(): void
    {
        foreach ([Passport::authCodeModel(), Passport::tokenModel()] as $model) {
            $model::creating(fn (Model $record) => $record->forceFill(AuthorizationContext::current()->pull()));
        }
    }

    /**
     * Register rate limiters for the OAuth2 / OpenID Connect endpoints.
     *
     * These endpoints are reached by unauthenticated clients, so limits are
     * keyed by IP address. The sizes come from "config/sso.php".
     */
    private function configureRateLimiting(): void
    {
        /*
         * "routes/oidc.php" applies this limiter to /oauth/authorize as well as
         * to the discovery endpoints, so it is sized for browser traffic that
         * may arrive from many users behind a single address.
         */
        RateLimiter::for('sso-discovery', fn (Request $request) => Limit::perMinute(
            (int) config('sso.rate_limits.discovery')
        )->by($request->ip()));

        RateLimiter::for('sso-token', fn (Request $request) => Limit::perMinute(
            (int) config('sso.rate_limits.token')
        )->by($request->ip()));

        RateLimiter::for('sso-userinfo', fn (Request $request) => Limit::perMinute(
            (int) config('sso.rate_limits.userinfo')
        )->by($request->ip()));
    }
}
