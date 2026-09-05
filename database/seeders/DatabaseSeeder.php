<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds what this server needs in order to function at all.
 *
 * Only the platform roles and permissions: an Identity Provider has no users
 * or applications until an operator creates them. Run
 * "php artisan db:seed --class=SsoDemoSeeder" for something to look at in
 * development.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlatformPermissionsSeeder::class);
    }
}
