<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The scopes this Identity Provider offers to applications.
 *
 * Passport::scopes() is deliberately not used as the source here. Other
 * packages register their own scopes with Passport — laravel/mcp adds
 * "mcp:use", for example — and those are not advertised by our discovery
 * document. Reading "config/oidc-server.php" keeps the administration
 * interface, validation and discovery describing the same set.
 */
class ScopeRegistry
{
    /**
     * Every scope an application may be granted, with its description.
     *
     * @return array<int, array{id: string, description: string}>
     */
    public function all(): array
    {
        /** @var array<string, array{description?: string}> $scopes */
        $scopes = config('oidc-server.scopes', []);

        return array_map(fn (string $id): array => [
            'id' => $id,
            'description' => $scopes[$id]['description'] ?? $id,
        ], array_keys($scopes));
    }

    /**
     * The identifiers of every scope an application may be granted.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys((array) config('oidc-server.scopes', []));
    }
}
