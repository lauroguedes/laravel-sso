<?php

namespace App\Models;

use Admin9\OidcServer\Concerns\HasOidcClaims;
use Admin9\OidcServer\Contracts\OidcUserInterface;
use App\Events\UserDisabled;
use App\Events\UserEnabled;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable, OidcUserInterface, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /*
     * The trait's resolver is aliased rather than called through parent::,
     * because a class method silently shadows the trait method it overrides
     * and parent:: would look at Illuminate's Authenticatable instead.
     */
    use HasOidcClaims {
        resolveOidcClaim as protected resolveDefaultOidcClaim;
    }

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
     * Scope the query to users matching a search term.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $query, string $term): void {
            /*
             * An address is matched by prefix, which an index on "email" can
             * serve, and so is each word of a name: an administrator looking
             * for "Hopper" should find "Grace Hopper", without the leading
             * wildcard that forces a scan of the whole table.
             */
            $query->where(fn (Builder $query) => $query
                ->where('email', 'like', "{$term}%")
                ->orWhere('name', 'like', "{$term}%")
                ->orWhere('name', 'like', "% {$term}%")
            );
        });
    }

    /**
     * Resolve a single OpenID Connect claim.
     *
     * Claims that are a plain attribute read are mapped in
     * "config/oidc-server.php". Derived claims are computed here so that the
     * configuration stays free of closures and "config:cache" keeps working.
     */
    protected function resolveOidcClaim(string $claim): mixed
    {
        return match ($claim) {
            'email_verified' => $this->email_verified_at !== null,
            'updated_at' => $this->updated_at?->timestamp,
            default => $this->resolveDefaultOidcClaim($claim),
        };
    }
}
