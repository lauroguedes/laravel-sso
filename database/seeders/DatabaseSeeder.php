<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds what this server needs in order to function at all.
 *
 * Only the platform roles and permissions: an Identity Provider has no users
 * or applications until an operator creates them. Run
 * "php artisan db:seed --class=SsoDemoSeeder" for something to look at in
 * development.
 *
 * Deliberately without WithoutModelEvents, which the starter kit ships here.
 * spatie/laravel-permission invalidates its permission cache from Eloquent
 * model events; muting them leaves roles being granted against a cache that
 * predates the permissions, and "migrate:refresh --seed" dies on a permission
 * it created seconds earlier.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlatformPermissionsSeeder::class);
    }
}
