<?php

use App\Enums\PlatformPermission;
use App\Models\Application;
use App\Models\ApplicationPermission;
use App\Models\ApplicationRole;
use App\Models\ApplicationUser;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->application = Application::factory()->create(['name' => 'Reporting']);
});

test('the roles page lists an application roles and permissions', function () {
    $permission = ApplicationPermission::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'reports.view',
    ]);

    $role = ApplicationRole::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'Analyst',
    ]);
    $role->permissions()->attach($permission);

    $response = $this->actingAs($this->admin)
        ->get(route('applications.roles.index', $this->application));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('applications/Roles')
        ->has('roles', 1)
        ->where('roles.0.name', 'Analyst')
        ->where('roles.0.permissions', [['id' => $permission->id, 'name' => 'reports.view']])
        ->has('permissions', 1));
});

test('an administrator defines a permission', function () {
    $this->actingAs($this->admin)
        ->from(route('applications.roles.index', $this->application))
        ->post(route('applications.permissions.store', $this->application), [
            'name' => 'reports.export',
            'description' => 'Download report data',
        ]);

    expect($this->application->permissions()->pluck('name')->all())
        ->toBe(['reports.export']);
});

test('rejects a permission name that is not a dotted lowercase identifier', function (string $name) {
    $response = $this->actingAs($this->admin)
        ->from(route('applications.roles.index', $this->application))
        ->post(route('applications.permissions.store', $this->application), ['name' => $name]);

    $response->assertSessionHasErrors('name');
})->with([
    'uppercase' => 'Reports.View',
    'spaces' => 'reports view',
    'trailing dot' => 'reports.',
    'leading dot' => '.reports',
]);

test('rejects a permission already defined for the application', function () {
    ApplicationPermission::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'reports.view',
    ]);

    $response = $this->actingAs($this->admin)
        ->from(route('applications.roles.index', $this->application))
        ->post(route('applications.permissions.store', $this->application), ['name' => 'reports.view']);

    $response->assertSessionHasErrors('name');
});

test('the same permission name may exist in two applications', function () {
    $billing = Application::factory()->create(['name' => 'Billing']);

    ApplicationPermission::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'reports.view',
    ]);

    $response = $this->actingAs($this->admin)
        ->from(route('applications.roles.index', $billing))
        ->post(route('applications.permissions.store', $billing), ['name' => 'reports.view']);

    $response->assertSessionHasNoErrors();

    expect($billing->permissions()->count())->toBe(1);
});

test('an administrator defines a role with permissions', function () {
    $view = ApplicationPermission::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'reports.view',
    ]);
    $export = ApplicationPermission::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'reports.export',
    ]);

    $this->actingAs($this->admin)
        ->from(route('applications.roles.index', $this->application))
        ->post(route('applications.roles.store', $this->application), [
            'name' => 'Analyst',
            'permissions' => [$view->id, $export->id],
        ]);

    $role = $this->application->roles()->sole();

    expect($role->name)->toBe('Analyst')
        ->and($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['reports.view', 'reports.export']);
});

test('a role cannot be given a permission belonging to another application', function () {
    $billing = Application::factory()->create(['name' => 'Billing']);

    $foreign = ApplicationPermission::factory()->create([
        'application_id' => $billing->id,
        'name' => 'invoices.view',
    ]);

    $response = $this->actingAs($this->admin)
        ->from(route('applications.roles.index', $this->application))
        ->post(route('applications.roles.store', $this->application), [
            'name' => 'Analyst',
            'permissions' => [$foreign->id],
        ]);

    $response->assertSessionHasErrors('permissions.0');
});

test('rejects a role name already used in the same application', function () {
    ApplicationRole::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'Analyst',
    ]);

    $response = $this->actingAs($this->admin)
        ->from(route('applications.roles.index', $this->application))
        ->post(route('applications.roles.store', $this->application), ['name' => 'Analyst']);

    $response->assertSessionHasErrors('name');
});

test('an administrator changes the permissions a role grants', function () {
    $view = ApplicationPermission::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'reports.view',
    ]);
    $manage = ApplicationPermission::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'reports.manage',
    ]);

    $role = ApplicationRole::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'Analyst',
    ]);
    $role->permissions()->attach($view);

    $this->actingAs($this->admin)
        ->from(route('applications.roles.index', $this->application))
        ->put(route('applications.roles.update', [$this->application, $role]), [
            'name' => 'Analyst',
            'permissions' => [$manage->id],
        ]);

    expect($role->refresh()->permissions->pluck('name')->all())->toBe(['reports.manage']);
});

test('removing a role leaves the access of users who held it', function () {
    $user = User::factory()->create();

    $role = ApplicationRole::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'Analyst',
    ]);

    $grant = ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => $user->id,
        'application_role_id' => $role->id,
    ]);

    $this->actingAs($this->admin)
        ->from(route('applications.roles.index', $this->application))
        ->delete(route('applications.roles.destroy', [$this->application, $role]));

    $this->assertModelMissing($role);

    expect($grant->refresh()->application_role_id)->toBeNull();
});

test('a role belonging to another application cannot be edited through this one', function () {
    $billing = Application::factory()->create(['name' => 'Billing']);

    $foreignRole = ApplicationRole::factory()->create([
        'application_id' => $billing->id,
        'name' => 'Viewer',
    ]);

    $this->actingAs($this->admin)
        ->put(route('applications.roles.update', [$this->application, $foreignRole]), [
            'name' => 'Hijacked',
        ])
        ->assertNotFound();

    expect($foreignRole->refresh()->name)->toBe('Viewer');
});

describe('permission isolation', function () {
    test('view permission alone does not allow defining a role', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::ApplicationsView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::ApplicationsView->value);

        $this->actingAs($viewer)
            ->get(route('applications.roles.index', $this->application))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('applications.roles.store', $this->application), ['name' => 'Analyst'])
            ->assertForbidden();
    });

    test('a user without any platform permission cannot see the roles page', function () {
        assertPageRefused(
            $this->actingAs(User::factory()->create())->get(route('applications.roles.index', $this->application))
        );
    });
});
