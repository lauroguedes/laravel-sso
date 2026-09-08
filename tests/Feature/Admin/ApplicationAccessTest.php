<?php

use App\Enums\PlatformPermission;
use App\Events\UserApplicationAccessGranted;
use App\Events\UserApplicationAccessRevoked;
use App\Events\UserApplicationRoleChanged;
use App\Models\Application;
use App\Models\ApplicationRole;
use App\Models\ApplicationUser;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->application = Application::factory()->create(['name' => 'Reporting']);
    $this->analyst = ApplicationRole::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'Analyst',
    ]);
});

test('the access page lists who may sign in and their role', function () {
    $user = User::factory()->create(['name' => 'Alice Smith']);

    ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => $user->id,
        'application_role_id' => $this->analyst->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('applications.grants.index', $this->application));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('applications/Access')
        ->has('grants.data', 1)
        ->where('grants.data.0.user.name', 'Alice Smith')
        ->where('grants.data.0.role_id', $this->analyst->id));
});

test('the candidate list excludes users who already have access', function () {
    $withAccess = User::factory()->create(['name' => 'Alice Smith']);
    User::factory()->create(['name' => 'Bob Jones']);

    ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => $withAccess->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('applications.grants.index', $this->application));

    $response->assertInertia(fn ($page) => $page->where(
        'candidates',
        fn ($candidates) => ! collect($candidates)->pluck('id')->contains($withAccess->id),
    ));
});

test('the candidate list can be searched', function () {
    User::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
    User::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com']);

    $response = $this->actingAs($this->admin)
        ->get(route('applications.grants.index', [$this->application, 'search' => 'alice']));

    $response->assertInertia(fn ($page) => $page->where(
        'candidates',
        fn ($candidates) => collect($candidates)->pluck('email')->all() === ['alice@example.com'],
    ));
});

test('an administrator grants a user access', function () {
    Event::fake([UserApplicationAccessGranted::class]);

    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->from(route('applications.grants.index', $this->application))
        ->post(route('applications.grants.store', $this->application), [
            'user_id' => $user->id,
            'application_role_id' => $this->analyst->id,
        ]);

    $grant = ApplicationUser::query()
        ->where('application_id', $this->application->id)
        ->where('user_id', $user->id)
        ->sole();

    expect($grant->application_role_id)->toBe($this->analyst->id);

    Event::assertDispatched(UserApplicationAccessGranted::class);
});

test('a user may be granted access without a role', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->from(route('applications.grants.index', $this->application))
        ->post(route('applications.grants.store', $this->application), [
            'user_id' => $user->id,
            'application_role_id' => null,
        ]);

    expect(ApplicationUser::query()->where('user_id', $user->id)->sole()->application_role_id)
        ->toBeNull();
});

test('rejects granting access twice to the same user', function () {
    $user = User::factory()->create();

    ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->from(route('applications.grants.index', $this->application))
        ->post(route('applications.grants.store', $this->application), ['user_id' => $user->id]);

    $response->assertSessionHasErrors('user_id');
});

test('rejects a role belonging to another application', function () {
    $billing = Application::factory()->create(['name' => 'Billing']);
    $foreignRole = ApplicationRole::factory()->create(['application_id' => $billing->id]);

    $response = $this->actingAs($this->admin)
        ->from(route('applications.grants.index', $this->application))
        ->post(route('applications.grants.store', $this->application), [
            'user_id' => User::factory()->create()->id,
            'application_role_id' => $foreignRole->id,
        ]);

    $response->assertSessionHasErrors('application_role_id');
});

test('an administrator changes the role a user holds', function () {
    $user = User::factory()->create();
    $viewer = ApplicationRole::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'Viewer',
    ]);

    $grant = ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => $user->id,
        'application_role_id' => $this->analyst->id,
    ]);

    $this->actingAs($this->admin)
        ->from(route('applications.grants.index', $this->application))
        ->put(route('applications.grants.update', [$this->application, $grant]), [
            'application_role_id' => $viewer->id,
        ]);

    expect($grant->refresh()->application_role_id)->toBe($viewer->id);
});

test('changing a role through the model raises the event for every caller', function () {
    Event::fake([UserApplicationRoleChanged::class]);

    $viewer = ApplicationRole::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'Viewer',
    ]);

    $grant = ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => User::factory()->create()->id,
        'application_role_id' => $this->analyst->id,
    ]);

    $grant->assignRole($viewer);

    Event::assertDispatched(
        UserApplicationRoleChanged::class,
        fn (UserApplicationRoleChanged $event): bool => $event->from?->name === 'Analyst'
    );
});

test('reassigning the same role raises nothing', function () {
    Event::fake([UserApplicationRoleChanged::class]);

    $grant = ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => User::factory()->create()->id,
        'application_role_id' => $this->analyst->id,
    ]);

    $grant->assignRole($this->analyst);

    Event::assertNotDispatched(UserApplicationRoleChanged::class);
});

test('granting through the model raises the event for every caller', function () {
    Event::fake([UserApplicationAccessGranted::class]);

    $this->application->grantAccessTo(User::factory()->create(), $this->analyst);

    Event::assertDispatched(UserApplicationAccessGranted::class);
});

test('an administrator clears the role a user holds', function () {
    $user = User::factory()->create();

    $grant = ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => $user->id,
        'application_role_id' => $this->analyst->id,
    ]);

    $this->actingAs($this->admin)
        ->from(route('applications.grants.index', $this->application))
        ->put(route('applications.grants.update', [$this->application, $grant]), [
            'application_role_id' => null,
        ]);

    expect($grant->refresh()->application_role_id)->toBeNull();
});

test('an administrator revokes access', function () {
    Event::fake([UserApplicationAccessRevoked::class]);

    $user = User::factory()->create();

    $grant = ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($this->admin)
        ->from(route('applications.grants.index', $this->application))
        ->delete(route('applications.grants.destroy', [$this->application, $grant]));

    $this->assertModelMissing($grant);

    Event::assertDispatched(UserApplicationAccessRevoked::class);
});

test('a grant belonging to another application cannot be changed through this one', function () {
    $billing = Application::factory()->create(['name' => 'Billing']);

    $foreignGrant = ApplicationUser::create([
        'application_id' => $billing->id,
        'user_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('applications.grants.destroy', [$this->application, $foreignGrant]))
        ->assertNotFound();

    $this->assertModelExists($foreignGrant);
});

test('a user page shows the applications they may sign in to', function () {
    $user = User::factory()->create();

    ApplicationUser::create([
        'application_id' => $this->application->id,
        'user_id' => $user->id,
        'application_role_id' => $this->analyst->id,
    ]);

    $response = $this->actingAs($this->admin)->get(route('users.edit', $user));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->has('applicationAccess', 1)
        ->where('applicationAccess.0.application_name', 'Reporting')
        ->where('applicationAccess.0.role_name', 'Analyst'));
});

describe('permission isolation', function () {
    test('view permission alone does not allow granting access', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::ApplicationsView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::ApplicationsView->value);

        $this->actingAs($viewer)
            ->get(route('applications.grants.index', $this->application))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('applications.grants.store', $this->application), [
                'user_id' => User::factory()->create()->id,
            ])
            ->assertForbidden();
    });

    test('a user without any platform permission cannot see the access page', function () {
        assertPageRefused(
            $this->actingAs(User::factory()->create())->get(route('applications.grants.index', $this->application))
        );
    });
});
