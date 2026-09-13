<?php

use App\Models\Application;
use App\Models\User;
use App\Oidc\Exceptions\SigningKeyUnavailable;
use Illuminate\Support\Facades\Exceptions;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;

/**
 * Where the signing keys come from.
 *
 * Passport signs access tokens with PASSPORT_PRIVATE_KEY when it is set, and
 * with the key file otherwise. ID Tokens and the key set follow the same rule,
 * or a server configured through its environment would publish a key that
 * verifies none of its ID Tokens.
 */
test('keys supplied only through the environment sign every token and name the published key', function () {
    withoutKeyFiles(function () {
        $pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pair, $privateKey);
        $publicKey = openssl_pkey_get_details($pair)['key'];

        /*
         * An environment variable holds a key on one line, with a literal "\n"
         * for each line break.
         */
        config([
            'passport.private_key' => str_replace("\n", '\n', $privateKey),
            'passport.public_key' => str_replace("\n", '\n', $publicKey),
        ]);

        $tokens = issueTokens(User::factory()->create(), Application::factory()->trusted()->withSecret('env-secret')->create());
        $idToken = (new Parser(new JoseEncoder))->parse($tokens['id_token']);

        /*
         * The key id is derived from the public key as the OIDC package derived
         * it, so key sets relying parties have already cached stay valid.
         */
        expect((new Sha256)->verify($idToken->signature()->hash(), $idToken->payload(), InMemory::plainText($publicKey)))->toBeTrue()
            ->and($idToken->headers()->get('kid'))
            ->toBe($this->getJson('/.well-known/jwks.json')->json('keys.0.kid'))
            ->toBe(substr(hash('sha256', $publicKey), 0, 16));

        $this->getJson('/oauth/userinfo', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertOk();
    });
});

test('a server without keys answers the key set with an error, and reports it', function () {
    Exceptions::fake();

    withoutKeyFiles(fn () => $this->getJson('/.well-known/jwks.json')
        ->assertStatus(500)
        ->assertExactJson([
            'error' => 'Public key not found',
            'error_description' => 'The OAuth public key has not been generated.',
        ]));

    Exceptions::assertReported(SigningKeyUnavailable::class);
});
