<?php

declare(strict_types=1);

namespace App\Oidc\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * No usable key is available to sign or verify ID Tokens.
 *
 * A fault in this server's setup rather than in the caller's request, so
 * unlike OAuthError it is reported. The client still receives a JSON error.
 */
class SigningKeyUnavailable extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        string $description,
    ) {
        parent::__construct($description);
    }

    /**
     * Render the error as the JSON body the key set endpoint answers with.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->error,
            'error_description' => $this->getMessage(),
        ], 500);
    }
}
