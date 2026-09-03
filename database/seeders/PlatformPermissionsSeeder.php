<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permissions and roles that govern the SSO platform itself.
 *
 * Safe to run repeatedly: every record is matched on its name, so re-running
 * reconciles the set rather than duplicating it. Permissions removed from the
 * enum are deliberately left in place, since they may still be attached to a
 * role an operator created.
 */
class PlatformPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (PlatformPermission::cases() as $permission) {
                Permission::findOrCreate($permission->value, 'web');
            }

            foreach (PlatformRole::cases() as $role) {
                Role::findOrCreate($role->value, 'web')
                    ->givePermissionTo($role->permissions());
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
