<?php

use App\Enums\ApplicationType;
use App\Enums\PlatformRole;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\SsoDemoSeeder;

describe('with the demo data seeded', function () {
    beforeEach(fn () => $this->seed(SsoDemoSeeder::class));

    test('it creates users and applications an operator can explore', function () {
        expect(User::count())->toBe(5)
            ->and(User::role(PlatformRole::SuperAdmin->value)->count())->toBe(1)
            ->and(User::role(PlatformRole::Developer->value)->count())->toBe(1)
            ->and(User::whereNotNull('disabled_at')->count())->toBe(1)
            ->and(Application::count())->toBe(2);
    });

    test('it demonstrates a developer who looks after one application', function () {
        /*
         * One of the two, not both: the role reaches nothing on its own, and
         * the demo only shows that if the assignment is narrower than the
         * role.
         */
        $developer = User::role(PlatformRole::Developer->value)->sole();

        expect($developer->managedApplications()->pluck('name')->all())->toBe(['Reporting']);
    });

    test('it demonstrates both kinds of interactive client', function () {
        $types = Application::all()->map(fn (Application $application): string => $application->type()->value);

        expect($types->all())->toEqualCanonicalizing([
            ApplicationType::Confidential->value,
            ApplicationType::Public->value,
        ]);
    });

    test('it demonstrates per-application roles and permissions', function () {
        $reporting = Application::where('name', 'Reporting')->sole();

        expect($reporting->roles()->count())->toBe(3)
            ->and($reporting->permissions()->count())->toBe(3)
            ->and($reporting->roles()->where('name', 'Viewer')->sole()->permissions()->count())->toBe(1)
            ->and($reporting->roles()->where('name', 'Owner')->sole()->permissions()->count())->toBe(3);
    });

    test('the restricted application admits only the users it was given', function () {
        $reporting = Application::where('name', 'Reporting')->sole();
        $granted = User::where('email', 'grace@'.SsoDemoSeeder::DOMAIN)->sole();
        $ungranted = User::where('email', 'former@'.SsoDemoSeeder::DOMAIN)->sole();

        expect($reporting->restricts_access)->toBeTrue()
            ->and($reporting->admits($granted))->toBeTrue()
            ->and($reporting->admits($ungranted))->toBeFalse();
    });

    test('the demo administrator can sign in with the published password', function () {
        $this->post('/login', [
            'email' => 'admin@'.SsoDemoSeeder::DOMAIN,
            'password' => SsoDemoSeeder::PASSWORD,
        ]);

        $this->assertAuthenticated();
    });
});

test('it refuses to run in production', function () {
    /*
     * Every account it creates shares one published password, so the guard is
     * the seeder's own rather than a note in the documentation.
     */
    $this->app->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(SsoDemoSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'will not run in production');

    expect(User::count())->toBe(0);
});
