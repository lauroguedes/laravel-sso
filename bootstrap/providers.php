<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\OidcServiceProvider;
use App\Providers\SettingsServiceProvider;

return [
    AppServiceProvider::class,
    SettingsServiceProvider::class,
    FortifyServiceProvider::class,
    OidcServiceProvider::class,
];
