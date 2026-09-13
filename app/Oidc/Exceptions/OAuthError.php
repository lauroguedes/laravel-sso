<?php

declare(strict_types=1);

namespace App\Oidc\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An error answered to the client in the shape OAuth 2.0 defines.
 *
 * Thrown anywhere in app/Oidc and rendered by the framework. It describes what was
 * wrong with the caller's request, so it is never reported.
 */
class OAuthError extends RuntimeException implements ShouldntReport
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly int $status,
        public readonly array $headers = [],
    ) {
        parent::__construct($description);
    }

    /**
     * The client could not be authenticated.
     */
    public static function invalidClient(): self
    {
        return new self('invalid_client', 'Client authentication failed.', 401);
    }

    /**
     * The request carried no valid access token.
     */
    public static function invalidToken(): self
    {
        return new self('invalid_token', 'The access token is invalid or expired.', 401);
    }

    /**
     * The access token was not granted the scope the resource requires.
     *
     * Announced in the WWW-Authenticate header as well as the body, as RFC 6750
     * section 3 requires of a protected resource.
     */
    public static function insufficientScope(string $scope): self
    {
        return new self('insufficient_scope', "The access token was not granted the {$scope} scope.", 403, [
            'WWW-Authenticate' => "Bearer error=\"insufficient_scope\", scope=\"{$scope}\"",
        ]);
    }

    /**
     * The token type hint names a type this server does not handle.
     */
    public static function unsupportedTokenType(): self
    {
        return new self('unsupported_token_type', 'token_type_hint must be access_token or refresh_token.', 400);
    }

    /**
     * Render the error as the JSON body OAuth 2.0 clients expect.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->error,
            'error_description' => $this->description,
        ], $this->status, $this->headers);
    }
}
