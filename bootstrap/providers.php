<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\SsoServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    SsoServiceProvider::class,
];
