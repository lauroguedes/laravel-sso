<?php

declare(strict_types=1);

namespace App\Providers;

use App\Oidc\Contracts\AuthenticatesClients;
use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\EndsSessions;
use App\Oidc\Contracts\IntrospectsTokens;
use App\Oidc\Contracts\ProvidesSigningKeys;
use App\Oidc\Contracts\ResolvesClaims;
use App\Oidc\Contracts\RevokesTokens;
use App\Oidc\OidcManager;
use App\Oidc\Passport\ClientRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\ClientRepository as PassportClientRepository;

/**
 * Binds each OpenID Connect port to the adapter named by "oidc.driver".
 *
 * Controllers and tests ask for a port, never for an adapter, so choosing a
 * different implementation is a change of configuration.
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
        ResolvesClaims::class => 'claims',
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
        $this->app->bind(PassportClientRepository::class, ClientRepository::class);

        foreach (self::PORTS as $port => $method) {
            $this->app->bind($port, fn (Application $app): object => $app->make(OidcManager::class)->driver()->{$method}());
        }
    }
}
