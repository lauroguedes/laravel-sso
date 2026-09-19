<?php

namespace App\Models;

use App\Enums\PlatformPermission;
use App\Events\UserDisabled;
use App\Events\UserEnabled;
use App\Oidc\Contracts\OidcUser;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A person who can authenticate through this Identity Provider.
 *
 * The model carries three concerns beyond the starter kit's authentication:
 * Passport (OAuth2 tokens), the OpenID Connect claim set exposed to relying
 * parties, and platform-level roles from spatie/laravel-permission.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $disabled_at
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable, OidcUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the user is allowed to authenticate.
     */
    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    /**
     * Withdraw the user's ability to authenticate.
     *
     * Users are disabled rather than deleted so that audit history keeps
     * pointing at a real actor.
     *
     * The event is raised here rather than at the call site so that every
     * route to disabling a user — an administrator, a console command, a
     * listener — is audited, matching how ApplicationManager owns the events
     * on the application side.
     */
    public function disable(): void
    {
        if ($this->isDisabled()) {
            return;
        }

        $this->forceFill(['disabled_at' => $this->freshTimestamp()])->save();

        UserDisabled::dispatch($this);
    }

    /**
     * Restore the user's ability to authenticate.
     */
    public function enable(): void
    {
        if (! $this->isDisabled()) {
            return;
        }

        $this->forceFill(['disabled_at' => null])->save();

        UserEnabled::dispatch($this);
    }

    /** Whether this user stewards anything at all, once answered. */
    private ?bool $stewardsAnything = null;

    /**
     * The applications this user looks after.
     *
     * Not the same as the ones they can sign in to: this is stewardship —
     * changing what an application is — and applicationGrants() is access.
     *
     * @return BelongsToMany<Application, $this>
     */
    public function managedApplications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_managers')->withTimestamps();
    }

    /**
     * Determine whether this user looks after any application at all.
     *
     * What decides whether the applications section exists for them, so a
     * developer with nothing assigned yet is not offered an empty listing.
     */
    public function stewardsApplications(): bool
    {
        if (! $this->can(PlatformPermission::ApplicationsDevelop->value)) {
            return false;
        }

        /*
         * Memoized: ApplicationPolicy::viewAny answers with it, and that runs
         * on every Inertia response — including the partial reload behind each
         * keystroke in a search box.
         */
        return $this->stewardsAnything ??= $this->managedApplications()->exists();
    }

    /**
     * The applications this user has been granted access to.
     *
     * @return HasMany<ApplicationUser, $this>
     */
    public function applicationGrants(): HasMany
    {
        return $this->hasMany(ApplicationUser::class);
    }

    /**
     * Scope the query to active or disabled users.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'active' => $query->whereNull('disabled_at'),
            'disabled' => $query->whereNotNull('disabled_at'),
            'unverified' => $query->whereNull('email_verified_at'),
            default => $query,
        };
    }

    /**
     * Scope the query to holders of one platform role.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithRole(Builder $query, ?string $role): Builder
    {
        return $query->when(
            $role,
            fn (Builder $query, string $role) => $query->whereHas(
                'roles',
                fn ($roles) => $roles->where('name', $role),
            ),
        );
    }

    /**
     * The columns a listing of users may be ordered by.
     *
     * Lives here beside scopeSearch, because which columns are sortable is a
     * fact about the table rather than about one controller. The value arrives
     * in a query parameter and is spliced into an ORDER BY clause, so anything
     * absent from this list is ignored.
     *
     * @return array<int, string>
     */
    public static function sortableColumns(): array
    {
        return ['name', 'last_login_at', 'created_at'];
    }

    /**
     * Scope the query to users matching a search term.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $query, string $term): void {
            /*
             * An address is matched by prefix, and so is each word of a name,
             * so "hopper" finds "Grace Hopper". whereLike ignores letter case
             * on every database, PostgreSQL included. The word match rules out
             * an index, which is fine at the size of a users table.
             */
            $query->where(fn (Builder $query) => $query
                ->whereLike('email', "{$term}%")
                ->orWhereLike('name', "{$term}%")
                ->orWhereLike('name', "% {$term}%")
            );
        });
    }

    /**
     * The stable identifier relying parties know this user by.
     */
    public function getOidcSubject(): string
    {
        return (string) $this->getKey();
    }

    /**
     * The value of one OpenID Connect claim. A new claim is a line here and a line in its scope.
     */
    public function resolveOidcClaim(string $claim): mixed
    {
        return match ($claim) {
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'updated_at' => $this->updated_at?->timestamp,
            default => null,
        };
    }
}
