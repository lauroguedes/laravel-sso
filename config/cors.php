<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| A browser application, such as a single-page app registered as "SPA /
| Mobile", calls this server from another origin, so the browser asks
| first. Only the endpoints such an application calls answer: discovery and
| the key set to configure itself, the token endpoint to redeem a code and
| refresh, UserInfo, and revocation at sign-out.
|
| Introspection is left out, because only a confidential client may call it
| and a browser cannot keep a secret.
|
| SSO_CORS_ALLOWED_ORIGINS lists the origins, comma separated, as scheme,
| host and port. Left empty, no other origin is answered.
|
*/

return [

    'paths' => [
        '.well-known/openid-configuration',
        '.well-known/jwks.json',
        'oauth/token',
        'oauth/userinfo',
        'oauth/revoke',
    ],

    'allowed_methods' => ['GET', 'POST'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SSO_CORS_ALLOWED_ORIGINS', '')),
    ))),

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type'],

    /*
     * UserInfo explains a refusal in this header, which a browser hides from
     * JavaScript unless it is listed.
     */
    'exposed_headers' => ['WWW-Authenticate'],

    /*
     * Browsers ask before every call that carries a token, so let them keep
     * the answer. Removing an origin still takes effect at once, because every
     * response names the origin it allows.
     */
    'max_age' => 7200,

];
