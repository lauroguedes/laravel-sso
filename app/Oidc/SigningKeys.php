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
 */
class SigningKeys implements ProvidesSigningKeys
{
    public function __construct(private readonly Repository $config) {}

    public function privateKey(): string
    {
        return $this->read('private');
    }

    public function publicKey(): string
    {
        return $this->read('public');
    }

    public function keyId(): string
    {
        return self::keyIdFor($this->publicKey());
    }

    public function keySet(): array
    {
        $publicKey = $this->publicKey();
        $key = openssl_pkey_get_public($publicKey);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        if ($details === false || ! isset($details['rsa'])) {
            throw new SigningKeyUnavailable('Invalid public key', 'Unable to parse the public key.');
        }

        $encoder = new JoseEncoder;

        return ['keys' => [[
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => self::keyIdFor($publicKey),
            'n' => $encoder->base64UrlEncode($details['rsa']['n']),
            'e' => $encoder->base64UrlEncode($details['rsa']['e']),
        ]]];
    }

    /**
     * One half of the pair, from the environment or else its file.
     *
     * An environment variable holds the key on one line, so a literal "\n"
     * stands for each line break, exactly as Passport reads it.
     *
     * @return non-empty-string
     */
    private function read(string $type): string
    {
        $key = str_replace('\\n', "\n", (string) $this->config->get("passport.{$type}_key"));

        if ($key !== '') {
            return $key;
        }

        $path = Passport::keyPath("oauth-{$type}.key");
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false || $contents === '') {
            throw new SigningKeyUnavailable(ucfirst($type).' key not found', "The OAuth {$type} key has not been generated.");
        }

        return $contents;
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
