<?php

declare(strict_types=1);

namespace App\Oidc;

use App\Oidc\Contracts\ProvidesSigningKeys;
use App\Oidc\Exceptions\SigningKeyUnavailable;
use Illuminate\Contracts\Config\Repository;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;

/**
 * The key pair Passport signs access tokens with, used for ID Tokens and the
 * key set as well.
 *
 * Read the way Passport reads it: PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY
 * when they are set, otherwise the files at Passport::keyPath(). Reading them
 * any other way lets an access token and an ID Token from the same server be
 * signed by different keys.
 *
 * The key set also publishes the public key retired by the last rotation, as
 * OpenID Connect Core 1.0 section 10.1.1 asks: "The JWK Set document at the
 * jwks_uri SHOULD retain recently decommissioned signing keys for a reasonable
 * period of time to facilitate a smooth transition." Without it, a rotation
 * would strand every token signed before it.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#RotateSigKeys
 */
class SigningKeys implements ProvidesSigningKeys
{
    public function __construct(private readonly Repository $config) {}

    public function privateKey(): string
    {
        return $this->active('private');
    }

    public function publicKey(): string
    {
        return $this->active('public');
    }

    public function keyId(): string
    {
        return self::keyIdFor($this->publicKey());
    }

    public function verificationKeys(): array
    {
        $active = $this->publicKey();
        $retired = $this->find('sso.previous_public_key', 'oauth-previous-public.key');

        if ($retired === null || $retired === $active) {
            return [$active];
        }

        /*
         * A retired key is optional, so a malformed one is left out and
         * reported rather than taking the key set, and with it every relying
         * party, down with it.
         */
        if (self::rsaDetails($retired) === null) {
            report(new SigningKeyUnavailable(
                'Invalid retired public key',
                'Unable to parse the retired public key, so it is not published.',
            ));

            return [$active];
        }

        return [$active, $retired];
    }

    public function keySet(): array
    {
        return ['keys' => array_map(self::jwkFor(...), $this->verificationKeys())];
    }

    /**
     * One half of the active pair, which a working server cannot do without.
     *
     * @return non-empty-string
     */
    private function active(string $type): string
    {
        return $this->find("passport.{$type}_key", "oauth-{$type}.key")
            ?? throw new SigningKeyUnavailable(ucfirst($type).' key not found', "The OAuth {$type} key has not been generated.");
    }

    /**
     * A key from the environment, or else from its file beside the active pair.
     *
     * An environment variable holds the key on one line, so a literal "\n"
     * stands for each line break, exactly as Passport reads it. The active pair
     * and the retired key are read the same way, so they cannot drift apart.
     *
     * @return non-empty-string|null
     */
    private function find(string $configKey, string $file): ?string
    {
        $key = str_replace('\\n', "\n", (string) $this->config->get($configKey));

        if ($key !== '') {
            return $key;
        }

        $path = Passport::keyPath($file);
        $contents = is_file($path) ? file_get_contents($path) : false;

        return $contents === false || $contents === '' ? null : $contents;
    }

    /**
     * The JSON Web Key for a public key.
     *
     * @return array{kty: string, alg: string, use: string, kid: string, n: string, e: string}
     */
    private static function jwkFor(string $publicKey): array
    {
        $rsa = self::rsaDetails($publicKey)
            ?? throw new SigningKeyUnavailable('Invalid public key', 'Unable to parse the public key.');

        $encoder = new JoseEncoder;

        return [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => self::keyIdFor($publicKey),
            'n' => $encoder->base64UrlEncode($rsa['n']),
            'e' => $encoder->base64UrlEncode($rsa['e']),
        ];
    }

    /**
     * The modulus and exponent of an RSA public key, or null when it is not one.
     *
     * @return array{n: string, e: string}|null
     */
    private static function rsaDetails(string $publicKey): ?array
    {
        $key = openssl_pkey_get_public($publicKey);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        return $details === false || ! isset($details['rsa']) ? null : $details['rsa'];
    }

    /**
     * The identifier published for a public key.
     *
     * Unchanged since version 1, so key sets that relying parties have already
     * cached stay valid.
     */
    private static function keyIdFor(string $publicKey): string
    {
        return substr(hash('sha256', $publicKey), 0, 16);
    }
}
