<?php

declare(strict_types=1);

namespace App\Providers;

use Admin9\OidcServer\Services\ClaimsService;
use App\Models\Application;
use App\Services\ApplicationClaimsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wires the Identity Provider's OAuth2 / OpenID Connect layer.
 *
 * Laravel Passport is the authorization server and admin9/laravel-oidc-server is
 * the OpenID Connect layer on top of it. Scopes, token lifetimes, and the
 * client model are configured by that package from "config/oidc-server.php", so
 * they are not repeated here. This provider only holds policy that neither
 * package decides for us.
 *
 * Two Passport features are deliberately left untouched:
 *
 * - The Implicit and Resource Owner Password Credentials grants are disabled
 *   by default. Passport::enableImplicitGrant() and enablePasswordGrant()
 *   must never be called; neither grant is advertised by discovery.
 * - Client secrets are hashed by Passport's Client model, and the plain text
 *   value only exists on the request that generated it. That is what allows
 *   the administration interface to show a secret exactly once.
 */
class SsoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * The OIDC package binds its claims service as a singleton in its own
     * register step, so the replacement is declared here to take precedence.
     * It gives claim resolution the one thing the package does not pass down,
     * which application is asking, and is shared under its own class too, so
     * every caller holds the same instance.
     */
    public function register(): void
    {
        $this->app->singleton(ApplicationClaimsService::class);
        $this->app->alias(ApplicationClaimsService::class, ClaimsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureConsentScreen();
    }

    /**
     * Render the consent screen through Inertia rather than the package's
     * bundled Blade view, so it matches the rest of the interface.
     *
     * Registered from a booted callback because the OIDC package points
     * Passport at its own view during its boot, and configuration cannot hold
     * a closure without breaking "config:cache".
     *
     * @see Application::skipsAuthorization() for when this is
     *      shown at all.
     */
    private function configureConsentScreen(): void
    {
        $this->app->booted(function (): void {
            Passport::authorizationView(
                fn (array $parameters): Response => Inertia::render('oauth/Authorize', [
                    'application' => [
                        'name' => $parameters['client']->name,
                        'description' => $parameters['client']->description,
                    ],
                    'scopes' => array_map(fn (Scope $scope): array => [
                        'id' => $scope->id,
                        'description' => $scope->description,
                    ], $parameters['scopes']),
                    'authToken' => $parameters['authToken'],
                ])->toResponse($parameters['request'])
            );
        });
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
