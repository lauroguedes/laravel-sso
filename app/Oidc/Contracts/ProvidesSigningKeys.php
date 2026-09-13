<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

use App\Oidc\Exceptions\SigningKeyUnavailable;

/**
 * Publishes the keys relying parties verify ID Tokens with.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7517
 */
interface ProvidesSigningKeys
{
    /**
     * The JSON Web Key Set served at /.well-known/jwks.json.
     *
     * @return array<string, mixed>
     *
     * @throws SigningKeyUnavailable when no usable key is available
     */
    public function keySet(): array;
}
