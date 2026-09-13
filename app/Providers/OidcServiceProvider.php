<?php

declare(strict_types=1);

namespace App\Providers;

use App\Oidc\Adapters\Native\Claims;
use App\Oidc\Adapters\Native\IdTokens;
use App\Oidc\Contracts\AuthenticatesClients;
use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\EndsSessions;
use App\Oidc\Contracts\IntrospectsTokens;
use App\Oidc\Contracts\IssuesIdTokens;
use App\Oidc\Contracts\ProvidesSigningKeys;
use App\Oidc\Contracts\ResolvesClaims;
use App\Oidc\Contracts\RevokesTokens;
use App\Oidc\Http\Controllers\AuthorizationController;
use App\Oidc\OidcManager;
use App\Oidc\Passport\AccessTokenRepository;
use App\Oidc\Passport\AuthCodeRepository;
use App\Oidc\Passport\AuthorizationContext;
use App\Oidc\Passport\ClientRepository;
use App\Oidc\Passport\IdTokenResponse;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use Laravel\Passport\ClientRepository as PassportClientRepository;
use Laravel\Passport\Passport;

/**
 * Wires the OpenID Connect layer into Passport.
 *
 * Each port is bound to the adapter named by "oidc.driver", so controllers and
 * tests ask for a port, never for an adapter. What belongs to this project
 * whichever adapter answers, its claims and what it adds to Passport's codes,
 * tokens and responses, is bound directly.
 */
class OidcServiceProvider extends ServiceProvider
{
    /**
     * Each port, and the adapter method that hands it out.
     *
     * @var array<class-string, string>
     */
    private const PORTS = [
        DiscoversProvider::class => 'discovery',
        ProvidesSigningKeys::class => 'signingKeys',
        AuthenticatesClients::class => 'clients',
        IntrospectsTokens::class => 'introspection',
        RevokesTokens::class => 'revocation',
        EndsSessions::class => 'sessions',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OidcManager::class);
        $this->app->scoped(AuthorizationContext::class);

        $this->app->bind(PassportClientRepository::class, ClientRepository::class);
        $this->app->bind(PassportAuthCodeRepository::class, AuthCodeRepository::class);
        $this->app->bind(PassportAccessTokenRepository::class, AccessTokenRepository::class);
        $this->app->bind(ResolvesClaims::class, Claims::class);
        $this->app->bind(IssuesIdTokens::class, IdTokens::class);

        /*
         * Passport gives its own authorization controller the session guard
         * this way, and a subclass is a different class to the container.
         */
        $this->app->when(AuthorizationController::class)
            ->needs(StatefulGuard::class)
            ->give(fn () => Auth::guard(config('passport.guard')));

        foreach (self::PORTS as $port => $method) {
            $this->app->bind($port, fn (Application $app): object => $app->make(OidcManager::class)->driver()->{$method}());
        }
    }

    /**
     * Bootstrap any application services.
     *
     * Every authorization code and access token records whatever the current
     * request holds for it, in the insert Passport already makes, and takes it
     * so it cannot reach a second one.
     *
     * The response type is set once the application has booted, because the
     * OIDC package points Passport at its own while it boots.
     */
    public function boot(): void
    {
        foreach ([Passport::authCodeModel(), Passport::tokenModel()] as $model) {
            $model::creating(fn (Model $record) => $record->forceFill(AuthorizationContext::current()->pull()));
        }

        $this->app->booted(function (): void {
            Passport::useAuthorizationServerResponseType(new IdTokenResponse);
        });
    }
}
