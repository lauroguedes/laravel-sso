<?php

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

/**
 * Every migration must be reversible.
 *
 * "migrate:refresh" and "migrate:rollback" are how a developer resets a local
 * database and how a deployment backs out a bad release, and both walk every
 * down() in turn. Nothing in an ordinary test run ever rolls a migration back,
 * so a missing down(), or one that drops a column while an index still covers
 * it, breaks the chain silently and is only discovered by hand.
 */
test('the whole stack rolls back and reapplies', function () {
    /*
     * Runs the real thing rather than inspecting the files: it catches a
     * missing down(), an index left behind, and anything else the database
     * objects to, in whatever form it is written.
     */
    $this->artisan('migrate:refresh')->assertSuccessful();
})->skip(
    fn (): bool => config('database.default') !== 'sqlite',
    'Rolls the whole schema back, so it only runs against the disposable sqlite test database.',
);

test('the default seeder survives a refresh', function () {
    /*
     * "migrate:refresh --seed" is the ordinary way to reset a development
     * database. It used to die on a permission the seeder had created seconds
     * earlier: spatie invalidates its permission cache from Eloquent model
     * events, and the starter kit's DatabaseSeeder muted them.
     */
    $this->artisan('migrate:refresh --seed')->assertSuccessful();

    expect(Role::where('name', PlatformRole::SuperAdmin->value)
        ->firstOrFail()
        ->permissions()
        ->count())->toBe(count(PlatformPermission::values()));
})->skip(
    fn (): bool => config('database.default') !== 'sqlite',
    'Rolls the whole schema back, so it only runs against the disposable sqlite test database.',
);

test('every migration declares a way back', function () {
    /*
     * The rollback above would catch this too, but only as an opaque failure
     * partway through. Naming the file is what makes it fixable, and it costs
     * one directory read.
     */
    $missing = collect(File::files(database_path('migrations')))
        ->reject(fn ($file): bool => str_contains(File::get($file->getPathname()), 'function down'))
        ->map(fn ($file): string => $file->getFilename())
        ->values();

    expect($missing->all())->toBe([]);
});
