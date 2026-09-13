<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of OAuth2 client an administrator can register.
 *
 * The type is not stored as a column: it is a shorthand for a combination of
 * grant types and whether a client secret exists. Passport derives
 * confidentiality from the presence of a secret, so the type is read back from
 * the client rather than duplicated alongside it.
 */
enum ApplicationType: string
{
    /** A server-side web application that can keep a secret. */
    case Confidential = 'confidential';

    /** A browser or native application that cannot keep a secret, so it uses PKCE. */
    case Public = 'public';

    /** A backend service authenticating as itself, with no user present. */
    case Machine = 'machine';

    /**
     * A short label for the administration interface.
     *
     * Kept to two words at most: the type appears in a badge in every listing
     * row, where a full sentence pushes the columns that identify the
     * application off the screen. The sentence is description()'s job.
     */
    public function label(): string
    {
        return match ($this) {
            self::Confidential => 'Web App',
            self::Public => 'SPA / Mobile',
            self::Machine => 'Service',
        };
    }

    /**
     * A short explanation shown beside the label when choosing a type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Confidential => 'Runs on a server and can store a client secret. Uses the authorization code flow.',
            self::Public => 'Runs in a browser or on a device and cannot store a secret. Uses the authorization code flow with PKCE.',
            self::Machine => 'Acts on its own behalf with no signed-in user. Uses the client credentials grant.',
        };
    }

    /**
     * Determine whether this type is issued a client secret.
     */
    public function isConfidential(): bool
    {
        return $this !== self::Public;
    }

    /**
     * Determine whether this type redirects a browser back to the client.
     */
    public function usesRedirectUris(): bool
    {
        return $this !== self::Machine;
    }

    /**
     * The OAuth2 grant types enabled for this kind of client.
     *
     * @return array<int, string>
     */
    public function grantTypes(): array
    {
        return match ($this) {
            self::Confidential, self::Public => ['authorization_code', 'refresh_token'],
            self::Machine => ['client_credentials'],
        };
    }

    /**
     * The scopes selected by default when registering this kind of client.
     *
     * Interactive applications almost always want the standard OpenID Connect
     * set; a machine to machine client has no user to describe. The list is
     * intersected with what the server actually offers, so removing a scope
     * from "config/oidc.php" cannot pre-select something that
     * validation would then reject.
     *
     * @param  array<int, string>  $offered
     * @return array<int, string>
     */
    public function defaultScopes(array $offered): array
    {
        $wanted = match ($this) {
            self::Confidential, self::Public => ['openid', 'profile', 'email'],
            self::Machine => [],
        };

        return array_values(array_intersect($wanted, $offered));
    }

    /**
     * Derive the type of an existing client from its stored attributes.
     *
     * @param  array<int, string>  $grantTypes
     */
    public static function fromClient(array $grantTypes, bool $isConfidential): self
    {
        if (in_array('client_credentials', $grantTypes, true)) {
            return self::Machine;
        }

        return $isConfidential ? self::Confidential : self::Public;
    }
}
