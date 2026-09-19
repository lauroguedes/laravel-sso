<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

/**
 * Describes this provider to relying parties.
 *
 * @see https://openid.net/specs/openid-connect-discovery-1_0.html
 */
interface DiscoversProvider
{
    /**
     * The issuer, exactly as every token and the document state it.
     */
    public function issuer(): string;

    /**
     * The document served at /.well-known/openid-configuration.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
