<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Application;
use Laravel\Passport\Client;
use Laravel\Passport\Database\Factories\ClientFactory;

/**
 * Extends Passport's own client factory so that the column defaults stay in
 * one place; only the states this project adds live here.
 */
class ApplicationFactory extends ClientFactory
{
    /** @var class-string<Application> */
    protected $model = Application::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ...parent::definition(),
            'redirect_uris' => ['https://'.$this->faker->domainName().'/auth/callback'],
            'scopes' => ['openid', 'profile', 'email'],
            'skips_authorization' => false,
        ];
    }

    /**
     * Skip the consent screen, as a first-party application does.
     */
    public function trusted(): static
    {
        return $this->state(['skips_authorization' => true]);
    }

    /**
     * Take the application out of service.
     */
    public function disabled(): static
    {
        return $this->state(['revoked' => true]);
    }

    /**
     * Use a known client secret, so a test can authenticate as the client.
     *
     * Passport hashes the stored value and only keeps the plain text on the
     * instance that generated it, which a factory-made model never is.
     */
    public function withSecret(string $secret): static
    {
        return $this->state(['secret' => $secret])
            ->afterCreating(function (Client $client) use ($secret): void {
                $client->plainSecret = $secret;
            });
    }

    /**
     * Allow the application to receive authorization claims.
     */
    public function withRolesScope(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scopes' => [...$attributes['scopes'] ?? [], 'roles'],
        ]);
    }
}
