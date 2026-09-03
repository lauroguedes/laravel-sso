<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a single OAuth2 redirect URI.
 *
 * Redirect URIs are compared by exact string match when an authorization
 * request arrives, so wildcards are never accepted here: a pattern would
 * simply never match, and accepting one would mislead an administrator into
 * believing a range of URLs was registered.
 *
 * HTTPS is required outside of loopback addresses, which stay available over
 * plain HTTP so native and local development clients can complete the flow.
 */
class RedirectUri implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if (str_contains($value, '*')) {
            $fail('The :attribute must not contain a wildcard. Redirect URIs are matched exactly.');

            return;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            $fail('The :attribute must be an absolute URL including a scheme and host.');

            return;
        }

        if (isset($parts['fragment'])) {
            $fail('The :attribute must not contain a fragment.');

            return;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must use the http or https scheme.');

            return;
        }

        if ($scheme === 'https' || ! config('sso.redirect_uris.require_https')) {
            return;
        }

        if ($this->isLoopback($parts['host']) && config('sso.redirect_uris.allow_insecure_loopback')) {
            return;
        }

        $fail('The :attribute must use HTTPS unless it points at a loopback address.');
    }

    /**
     * Determine whether the host is a loopback address.
     */
    private function isLoopback(string $host): bool
    {
        /** @var array<int, string> $hosts */
        $hosts = config('sso.redirect_uris.loopback_hosts', []);

        return in_array(strtolower($host), array_map('strtolower', $hosts), true);
    }
}
