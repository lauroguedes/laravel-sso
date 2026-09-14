<?php

use App\Http\Middleware\EnsureApplicationAdmitsUser;
use App\Http\Middleware\EnsurePkceIsUsed;
use App\Oidc\Http\Controllers\ApproveAuthorizationController;
use App\Oidc\Http\Controllers\AuthorizationController;
use App\Oidc\Http\Controllers\DiscoveryController;
use App\Oidc\Http\Controllers\IntrospectionController;
use App\Oidc\Http\Controllers\KeySetController;
use App\Oidc\Http\Controllers\LogoutController;
use App\Oidc\Http\Controllers\RevocationController;
use App\Oidc\Http\Controllers\UserInfoController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
|--------------------------------------------------------------------------
| OAuth 2.0 and OpenID Connect Routes
|--------------------------------------------------------------------------
|
| Passport's own routes are ignored, so every protocol endpoint is declared
| here, under the route names this application links by.
|
| Rate limiters are defined in App\Providers\OidcServiceProvider and tuned
| through "sso.rate_limits". The discovery limiter also covers the
| /oauth/authorize routes, so it is sized for browser traffic from many users
| behind a single address.
|
*/

Route::middleware(['web', 'throttle:sso-discovery'])->group(function (): void {
    /*
     * The authorization policies run on the GET alone. The approval and denial
     * that follow carry no client_id, because Passport keeps the request in
     * the session, and approving needs an auth token that only a GET which
     * passed these checks puts there.
     */
    Route::get('oauth/authorize', [AuthorizationController::class, 'authorize'])
        ->middleware([EnsurePkceIsUsed::class, EnsureApplicationAdmitsUser::class])
        ->name('passport.authorizations.authorize');

    Route::post('oauth/authorize', [ApproveAuthorizationController::class, 'approve'])->name('passport.authorizations.approve');
    Route::delete('oauth/authorize', [DenyAuthorizationController::class, 'deny'])->name('passport.authorizations.deny');
});

Route::middleware('web')->group(function (): void {
    /*
     * A relying party may post the request from its own page, which cannot
     * carry this server's token. The POST only redirects to the same GET.
     */
    Route::match(['get', 'post'], 'oauth/logout', LogoutController::class)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->name('oidc.logout');
    Route::post('oauth/logout/confirm', [LogoutController::class, 'confirm'])->name('oidc.logout.confirm');
});

Route::middleware('throttle:sso-discovery')->group(function (): void {
    Route::get('.well-known/openid-configuration', DiscoveryController::class)->name('oidc.discovery');
    Route::get('.well-known/jwks.json', KeySetController::class)->name('oidc.jwks');
});

Route::match(['get', 'post'], 'oauth/userinfo', UserInfoController::class)
    ->middleware('throttle:sso-userinfo')
    ->name('oidc.userinfo');

Route::middleware('throttle:sso-token')->group(function (): void {
    Route::post('oauth/token', [AccessTokenController::class, 'issueToken'])->name('passport.token');
    Route::post('oauth/introspect', IntrospectionController::class)->name('oidc.introspect');
    Route::post('oauth/revoke', RevocationController::class)->name('oidc.revoke');
});
