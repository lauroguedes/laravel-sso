<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

use Laravel\Passport\Client;

/**
 * A relying party's request to end the user's session, once its parameters
 * have been checked.
 */
class LogoutRequest
{
    public function __construct(
        public readonly ?Client $client,
        public readonly ?string $destination,
        public readonly ?string $subject,
    ) {}
}
