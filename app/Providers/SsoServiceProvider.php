<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Identity Provider's OAuth2 / OpenID Connect layer.
 *
 * Laravel Passport is the authorization server and admin9/laravel-oidc-server
 * is the OpenID Connect layer on top of it. Scopes, token lifetimes, the
 * client model and the id_token response type are configured by that package
 * from "config/oidc-server.php", so they are not repeated here. This provider
 * only holds policy that neither package decides for us.
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
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
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
         * The OIDC package applies this limiter to /oauth/authorize as well as
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
