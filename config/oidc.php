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
    | "native" is this project's own implementation. "admin9" wraps the
    | admin9/laravel-oidc-server package, and remains only until the package
    | is removed.
    |
    */

    'driver' => 'native',

];
