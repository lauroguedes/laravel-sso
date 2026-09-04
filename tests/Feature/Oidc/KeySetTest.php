<?php

use App\Models\Application;
use App\Models\User;

/**
 * A relying party picks the verification key out of the JWKS document by the
 * "kid" in the token header. If the two ever disagree, a strict client rejects
 * every token this server issues.
 */
const KEYSET_REDIRECT_URI = 'https://keys.example.com/auth/callback';

test('the id token names the key that signed it', function () {
    $user = User::factory()->create();

    $application = Application::factory()
        ->trusted()
        ->withSecret('keyset-secret')
        ->create(['redirect_uris' => [KEYSET_REDIRECT_URI]]);

    [$verifier, $challenge] = pkcePair();

    $authorization = authorizationRequest($user, $application, [
        'code_challenge' => $challenge,
    ]);

    parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

    $idToken = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $application->id,
        'client_secret' => 'keyset-secret',
        'redirect_uri' => KEYSET_REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->json('id_token');

    $headers = idTokenHeaders($idToken);

    $published = $this->getJson('/.well-known/jwks.json')->json('keys.0.kid');

    expect($headers->get('kid'))->toBe($published)
        ->and($headers->get('alg'))->toBe('RS256');
});
