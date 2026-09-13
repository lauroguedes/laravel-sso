<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

use App\Oidc\Exceptions\SigningKeyUnavailable;

/**
 * The key this provider signs with, and the key set that publishes it.
 *
 * The private key, public key and key id describe the active key. The key set
 * may publish more than that one, which is what lets relying parties follow a
 * rotation.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7517
 */
interface ProvidesSigningKeys
{
    /**
     * The PEM encoded private key ID Tokens are signed with.
     *
     * @return non-empty-string
     *
     * @throws SigningKeyUnavailable
     */
    public function privateKey(): string;

    /**
     * The PEM encoded public key that verifies them.
     *
     * @return non-empty-string
     *
     * @throws SigningKeyUnavailable
     */
    public function publicKey(): string;

    /**
     * The identifier of the active key, stamped on every ID Token.
     *
     * @throws SigningKeyUnavailable
     */
    public function keyId(): string;

    /**
     * The JSON Web Key Set served at /.well-known/jwks.json.
     *
     * @return array<string, mixed>
     *
     * @throws SigningKeyUnavailable
     */
    public function keySet(): array;
}
