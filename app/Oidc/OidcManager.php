<?php

declare(strict_types=1);

namespace App\Oidc;

use App\Oidc\Adapters\Admin9\Admin9Adapter;
use App\Oidc\Adapters\Native\NativeAdapter;
use App\Oidc\Contracts\OidcAdapter;
use Illuminate\Support\Manager;

/**
 * Resolves the adapter that implements OpenID Connect, named by "oidc.driver".
 *
 * Another implementation is added with extend(), or with a create method here,
 * and selected in configuration. Nothing else names an adapter class.
 */
class OidcManager extends Manager
{
    /**
     * Get an adapter instance.
     *
     * @param  string|null  $driver
     */
    public function driver($driver = null): OidcAdapter
    {
        return parent::driver($driver);
    }

    /**
     * Get the default adapter name.
     */
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('oidc.driver', 'native');
    }

    /**
     * This project's own implementation.
     *
     * Ports it does not implement yet are lent by the package adapter, which
     * is removed once every port has moved across.
     */
    protected function createNativeDriver(): OidcAdapter
    {
        return new NativeAdapter($this->driver('admin9'));
    }

    /**
     * The admin9/laravel-oidc-server package.
     */
    protected function createAdmin9Driver(): OidcAdapter
    {
        return $this->container->make(Admin9Adapter::class);
    }
}
