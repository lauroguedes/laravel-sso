<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\SsoServiceProvider;

return [
    AppServiceProvider::class,
    SettingsServiceProvider::class,
    FortifyServiceProvider::class,
    SsoServiceProvider::class,
];
