<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\UserCreated;
use App\Events\UserUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates and maintains the people who can authenticate through this server.
 *
 * Every write to a user account goes through here, whether it came from the
 * administration interface, from self-registration, or from the console. The
 * three used to do it themselves and had drifted apart: one wrapped its writes
 * in a transaction and the others did not, and roles were attached two
 * different ways.
 *
 * Validation is not this class's job. The rules live in the two concerns in
 * "app/Concerns", so a form request, a Fortify action and a console command
 * can each apply them in whatever way suits them and then arrive here with
 * attributes already checked.
 *
 * The domain events are raised here for the same reason ApplicationManager
 * raises its own: so that every caller is audited, not only the one that
 * remembered to.
 */
class UserManager
{
    /**
     * Register a user.
     *
     * "email_verified" marks the address as already confirmed, which is what
     * an administrator does when they have another reason to trust it. Roles
     * are platform roles, and govern this administration interface only.
     *
     * @param  array{name: string, email: string, password: string, email_verified?: bool, roles?: array<int, string>}  $attributes
     */
    public function create(array $attributes): User
    {
        $user = DB::transaction(function () use ($attributes): User {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ]);

            if ($attributes['email_verified'] ?? false) {
                $user->forceFill(['email_verified_at' => $user->freshTimestamp()])->save();
            }

            $user->syncRoles($attributes['roles'] ?? []);

            return $user;
        });

        UserCreated::dispatch($user);

        return $user;
    }

    /**
     * Change a user's details.
     *
     * A blank or absent password leaves the existing one in place, so that
     * editing a name is not a credential reset.
     *
     * @param  array{name: string, email: string, password?: string|null, email_verified?: bool, roles?: array<int, string>}  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        DB::transaction(function () use ($user, $attributes): void {
            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
            ]);

            /*
             * Changing an address invalidates the previous verification: the
             * old confirmation said nothing about the new address. An
             * administrator may still mark the new one as already verified.
             */
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if ($attributes['email_verified'] ?? false) {
                $user->email_verified_at ??= $user->freshTimestamp();
            } else {
                $user->email_verified_at = null;
            }

            if (filled($attributes['password'] ?? null)) {
                $user->password = $attributes['password'];
            }

            $user->save();

            $user->syncRoles($attributes['roles'] ?? []);
        });

        UserUpdated::dispatch($user);

        return $user;
    }
}
