<?php

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('it creates the platform roles and permissions', function () {
    Role::query()->delete();
    Permission::query()->delete();

    $this->artisan('sso:install')
        ->expectsConfirmation('No administrator exists yet. Create one now?', 'no')
        ->assertSuccessful();

    expect(Role::where('name', PlatformRole::SuperAdmin->value)->exists())->toBeTrue()
        ->and(Permission::count())->toBe(count(PlatformPermission::values()));
});

test('it reports the values a relying party needs', function () {
    config()->set('sso.issuer', 'https://auth.example.test');
    config()->set('sso.discovery_url', 'https://auth.example.test/.well-known/openid-configuration');

    $this->artisan('sso:install --no-interaction')
        ->expectsOutputToContain('https://auth.example.test/oauth/authorize')
        ->expectsOutputToContain('https://auth.example.test/.well-known/openid-configuration')
        ->assertSuccessful();
});

test('it offers to create an administrator when none exists', function () {
    $this->artisan('sso:install')
        ->expectsConfirmation('No administrator exists yet. Create one now?', 'yes')
        ->expectsQuestion('Email address', 'ada@example.test')
        ->expectsQuestion('Name', 'Ada Admin')
        ->expectsQuestion('Password', 'correct-horse-battery-staple')
        ->expectsQuestion('Confirm password', 'correct-horse-battery-staple')
        ->assertSuccessful();

    expect(User::where('email', 'ada@example.test')->sole()->hasRole(PlatformRole::SuperAdmin->value))
        ->toBeTrue();
});

test('it does not ask about an administrator when one already exists', function () {
    User::factory()->superAdmin()->create();

    $this->artisan('sso:install')->assertSuccessful();
});

test('it leaves existing signing keys untouched', function () {
    $path = Passport::keyPath('oauth-private.key');
    $before = File::get($path);

    $this->artisan('sso:install --no-interaction')->assertSuccessful();

    /*
     * Replacing the private key would invalidate every ID Token already
     * issued and every relying party's cached key set, so re-running the
     * installer must never do it.
     */
    expect(File::get($path))->toBe($before);
});

test('it generates signing keys when there are none', function () {
    withoutKeyFiles(function (string $directory) {
        $this->artisan('sso:install --no-interaction')->assertSuccessful();

        expect(File::exists($directory.'/oauth-private.key'))->toBeTrue()
            ->and(File::exists($directory.'/oauth-public.key'))->toBeTrue();
    });
});

test('it does not generate key files when the keys come from the environment', function () {
    withoutKeyFiles(function (string $directory) {
        config()->set('passport.private_key', 'a private key held in the environment');

        $this->artisan('sso:install --no-interaction')->assertSuccessful();

        expect(File::files($directory))->toBeEmpty();
    });
});

test('it warns when the issuer is not reached over HTTPS', function () {
    config()->set('sso.issuer', 'http://auth.example.test');
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->artisan('sso:install --no-interaction')
        ->expectsOutputToContain('is not HTTPS')
        ->assertSuccessful();
});

test('it warns when the openid scope is missing', function () {
    config()->set('oidc-server.scopes', ['profile' => []]);

    $this->artisan('sso:install --no-interaction')
        ->expectsOutputToContain('cannot issue an ID Token')
        ->assertSuccessful();
});

test('running it again changes nothing', function () {
    User::factory()->superAdmin()->create();

    $this->artisan('sso:install --no-interaction')->assertSuccessful();
    $this->artisan('sso:install --no-interaction')->assertSuccessful();

    expect(Permission::count())->toBe(count(PlatformPermission::values()))
        ->and(Role::where('name', PlatformRole::SuperAdmin->value)->count())->toBe(1);
});
