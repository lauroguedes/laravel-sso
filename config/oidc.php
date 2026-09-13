<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OpenID Connect Layer
|--------------------------------------------------------------------------
|
| Which implementation serves the endpoints declared in "routes/oidc.php".
| The protocol values (scopes, claims, token lifetimes and what discovery
| advertises) are still read from "config/oidc-server.php".
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | "native" is this project's own implementation, and lends from "admin9",
    | the admin9/laravel-oidc-server package, whatever it does not implement
    | yet.
    |
    */

    'driver' => 'native',

];
