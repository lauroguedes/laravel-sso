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
     * The PEM encoded public half of the active key.
     *
     * Verify with verificationKeys() instead. This key alone would reject every
     * token signed before the last rotation.
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
     * Every PEM encoded public key a token this provider signed may still be
     * verified with: the active key first, then the one retired by the last
     * rotation, for as long as it is configured.
     *
     * @return non-empty-list<non-empty-string>
     *
     * @throws SigningKeyUnavailable
     */
    public function verificationKeys(): array;

    /**
     * The JSON Web Key Set served at /.well-known/jwks.json.
     *
     * @return array<string, mixed>
     *
     * @throws SigningKeyUnavailable
     */
    public function keySet(): array;
}
