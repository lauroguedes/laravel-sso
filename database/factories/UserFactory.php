<?php

namespace Database\Factories;

use App\Enums\PlatformRole;
use App\Models\User;
use Database\Seeders\PlatformPermissionsSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has been disabled and cannot authenticate.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled_at' => now(),
        ]);
    }

    /**
     * Indicate that the user holds every platform permission.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            app(PlatformPermissionsSeeder::class)->run();

            $user->assignRole(PlatformRole::SuperAdmin->value);
        });
    }

    /**
     * Indicate that the user may look after the applications assigned to them.
     *
     * The role on its own reaches nothing: an administrator still has to
     * assign an application before it grants anything.
     */
    public function developer(): static
    {
        return $this->afterCreating(function (User $user): void {
            app(PlatformPermissionsSeeder::class)->run();

            $user->assignRole(PlatformRole::Developer->value);
        });
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
