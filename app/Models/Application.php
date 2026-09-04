<?php

declare(strict_types=1);

namespace App\Models;

use Admin9\OidcServer\Models\OidcClient;
use App\Enums\ApplicationType;
use App\Events\UserApplicationAccessGranted;
use Database\Factories\ApplicationFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Passport\Scope;
use Stringable;

/**
 * An OAuth2 / OpenID Connect client registered on this server.
 *
 * This IS the Passport client rather than a record that points at one: it
 * extends Passport's model and lives in the "oauth_clients" table. Passport
 * already owns the name, redirect URIs, grant types, hashed secret and the
 * revoked flag, so there is exactly one notion of an OAuth client here.
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $secret
 * @property array<int, string> $redirect_uris
 * @property array<int, string> $grant_types
 * @property array<int, string>|null $scopes
 * @property bool $revoked
 * @property bool $skips_authorization
 * @property bool $restricts_access
 */
class Application extends OidcClient
{
    /**
     * The model's default attribute values.
     *
     * Clients created straight through Passport's ClientRepository do not pass
     * this column, so without a default the freshly created instance would
     * report null until it was reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'skips_authorization' => false,
        'restricts_access' => false,
    ];

    /**
     * Create a new factory instance for the model.
     *
     * Passport's Client hard-codes its own factory, so the override has to be
     * repeated here for Application::factory() to pick up this project's
     * states.
     */
    protected static function newFactory(): Factory
    {
        return ApplicationFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * Passport's own casts are merged with the columns this project adds, so
     * "skips_authorization" reads back as a boolean rather than an integer.
     *
     * @return array<string, string|Stringable>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'skips_authorization' => 'boolean',
            'restricts_access' => 'boolean',
        ];
    }

    /**
     * Determine whether the consent screen should be skipped for this client.
     *
     * Passport's default treats every client without an owner as first party,
     * which would silently skip consent for every application an administrator
     * creates. Trust is an explicit decision here instead.
     *
     * @param  Scope[]  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return (bool) $this->skips_authorization;
    }

    /**
     * Determine whether the application is available for authentication.
     */
    public function isEnabled(): bool
    {
        return ! $this->revoked;
    }

    /**
     * Determine the kind of client this application represents.
     *
     * The type is derived rather than stored, and this is the one place that
     * derivation happens: form requests, policies and the Inertia payload all
     * read it from here rather than inspecting grant types themselves.
     */
    public function type(): ApplicationType
    {
        return ApplicationType::fromClient(
            $this->grant_types ?? [],
            $this->isConfidential(),
        );
    }

    /**
     * Determine whether the application authenticates with a client secret.
     *
     * A public client, such as a browser or native application, has no secret
     * and relies on PKCE instead.
     */
    public function isConfidential(): bool
    {
        return $this->confidential();
    }

    /**
     * The roles this application defines.
     *
     * @return HasMany<ApplicationRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(ApplicationRole::class);
    }

    /**
     * The permissions this application recognises.
     *
     * @return HasMany<ApplicationPermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(ApplicationPermission::class);
    }

    /**
     * The access grants issued for this application.
     *
     * @return HasMany<ApplicationUser, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(ApplicationUser::class);
    }

    /**
     * Determine whether a user may sign in to this application.
     *
     * An unrestricted application admits any authenticated user. A restricted
     * one admits only those an administrator has granted access to, which is
     * the same record that carries their role.
     */
    public function admits(User $user): bool
    {
        if (! $this->restricts_access) {
            return true;
        }

        return $this->grants()->where('user_id', $user->getKey())->exists();
    }

    /**
     * Grant a user access to this application, optionally with a role.
     *
     * The event is raised here so that every caller emits it, not only the
     * administration controller.
     */
    public function grantAccessTo(User $user, ?ApplicationRole $role = null): ApplicationUser
    {
        $grant = $this->grants()->create([
            'user_id' => $user->getKey(),
            'application_role_id' => $role?->getKey(),
        ]);

        UserApplicationAccessGranted::dispatch($grant);

        return $grant;
    }

    /**
     * Scope the query to applications matching a search term.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $query, string $term) => $query->where(
            fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('id', 'like', "%{$term}%")
        ));
    }
}
