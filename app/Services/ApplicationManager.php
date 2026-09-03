<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationType;
use App\Events\ApplicationCreated;
use App\Events\ApplicationDisabled;
use App\Events\ApplicationEnabled;
use App\Events\ApplicationUpdated;
use App\Events\ClientSecretRegenerated;
use App\Models\Application;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;

/**
 * Creates and maintains the OAuth2 clients behind administrator-managed
 * applications.
 *
 * Client records are always written through Passport's ClientRepository so
 * that secret generation and hashing stay in one place; the extra columns this
 * project adds are applied in the same transaction.
 */
class ApplicationManager
{
    public function __construct(private readonly ClientRepository $clients) {}

    /**
     * Register a new application.
     *
     * The returned model carries the plain text secret in "plainSecret" for
     * the remainder of this request only. It is never persisted in plain form
     * and cannot be recovered afterwards.
     *
     * @param  array{name: string, description?: string|null, type: ApplicationType, redirect_uris?: array<int, string>, scopes?: array<int, string>, skips_authorization?: bool}  $attributes
     */
    public function create(array $attributes): Application
    {
        $type = $attributes['type'];

        $application = DB::transaction(function () use ($attributes, $type): Application {
            /** @var Application $client */
            $client = $this->clients->createAuthorizationCodeGrantClient(
                name: $attributes['name'],
                redirectUris: $type->usesRedirectUris() ? ($attributes['redirect_uris'] ?? []) : [],
                confidential: $type->isConfidential(),
            );

            $plainSecret = $client->plainSecret;

            $client->forceFill([
                'description' => $attributes['description'] ?? null,
                'grant_types' => $type->grantTypes(),
                'scopes' => $attributes['scopes'] ?? [],
                'skips_authorization' => $attributes['skips_authorization'] ?? false,
            ])->save();

            $client->plainSecret = $plainSecret;

            return $client;
        });

        ApplicationCreated::dispatch($application);

        return $application;
    }

    /**
     * Update an application's editable attributes.
     *
     * The type, and therefore the grant types and the presence of a secret,
     * is fixed at creation: changing it would silently invalidate every
     * integration already using the client.
     *
     * @param  array{name: string, description?: string|null, redirect_uris?: array<int, string>, scopes?: array<int, string>, skips_authorization?: bool}  $attributes
     */
    public function update(Application $application, array $attributes): Application
    {
        $type = $this->typeOf($application);

        DB::transaction(function () use ($application, $attributes, $type): void {
            $application->forceFill([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'redirect_uris' => $type->usesRedirectUris() ? ($attributes['redirect_uris'] ?? []) : [],
                'scopes' => $attributes['scopes'] ?? [],
                'skips_authorization' => $attributes['skips_authorization'] ?? false,
            ])->save();
        });

        ApplicationUpdated::dispatch($application);

        return $application;
    }

    /**
     * Issue a new client secret, invalidating the previous one immediately.
     *
     * The plain text secret is returned so it can be shown once; it is stored
     * only as a hash.
     */
    public function regenerateSecret(Application $application): string
    {
        $this->clients->regenerateSecret($application);

        ClientSecretRegenerated::dispatch($application);

        return (string) $application->plainSecret;
    }

    /**
     * Disable an application and revoke every token it has issued.
     *
     * Leaving live tokens behind would let a disabled application keep calling
     * resource servers until its access tokens expired.
     */
    public function disable(Application $application): void
    {
        DB::transaction(fn () => $this->clients->delete($application));

        ApplicationDisabled::dispatch($application);
    }

    /**
     * Re-enable a previously disabled application.
     *
     * Tokens revoked while it was disabled stay revoked; clients obtain new
     * ones through the normal flow.
     */
    public function enable(Application $application): void
    {
        $application->forceFill(['revoked' => false])->save();

        ApplicationEnabled::dispatch($application);
    }

    /**
     * Determine the kind of client an application represents.
     */
    public function typeOf(Application $application): ApplicationType
    {
        return ApplicationType::fromClient(
            $application->grant_types ?? [],
            $application->isConfidential(),
        );
    }
}
