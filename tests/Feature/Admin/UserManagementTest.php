<?php

use App\Enums\PlatformPermission;
use App\Events\UserCreated;
use App\Events\UserDisabled;
use App\Events\UserUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
});

test('the user list is rendered for an administrator', function () {
    User::factory()->create(['name' => 'Alice Smith']);

    $response = $this->actingAs($this->admin)->get(route('users.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/Index')
            ->has('users.data', 2));
});

test('the user list can be filtered by name or email', function () {
    User::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
    User::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com']);

    $response = $this->actingAs($this->admin)->get(route('users.index', ['search' => 'alice']));

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.email', 'alice@example.com'));
});

test('an administrator creates a user', function () {
    Event::fake([UserCreated::class]);

    $response = $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'email_verified' => true,
    ]);

    $user = User::where('email', 'alice@example.com')->sole();

    $response->assertRedirect(route('users.edit', $user));

    expect($user->name)->toBe('Alice Smith')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->isDisabled())->toBeFalse();

    Event::assertDispatched(UserCreated::class);
});

test('a created user can sign in with the assigned password', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => 'alice@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);

    $this->assertAuthenticated();
});

test('rejects a user whose email is already registered', function () {
    User::factory()->create(['email' => 'alice@example.com']);

    $response = $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertSessionHasErrors('email');
});

test('rejects a password that does not match its confirmation', function () {
    $response = $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'something-else-entirely',
    ]);

    $response->assertSessionHasErrors('password');
});

test('an administrator updates a user without changing their password', function () {
    Event::fake([UserUpdated::class]);

    $user = User::factory()->create(['name' => 'Alice Smith']);
    $originalPassword = $user->password;

    $this->actingAs($this->admin)->put(route('users.update', $user), [
        'name' => 'Alice Cooper',
        'email' => $user->email,
        'email_verified' => true,
    ]);

    expect($user->refresh()->name)->toBe('Alice Cooper')
        ->and($user->password)->toBe($originalPassword);

    Event::assertDispatched(UserUpdated::class);
});

test('changing a user email clears the previous verification', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);

    $this->actingAs($this->admin)->put(route('users.update', $user), [
        'name' => $user->name,
        'email' => 'alice.smith@example.com',
        'email_verified' => false,
    ]);

    expect($user->refresh()->email)->toBe('alice.smith@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

test('an administrator assigns and removes platform roles', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->put(route('users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => ['Super Admin'],
    ]);

    expect($user->refresh()->hasRole('Super Admin'))->toBeTrue();

    $this->actingAs($this->admin)->put(route('users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => [],
    ]);

    expect($user->refresh()->hasRole('Super Admin'))->toBeFalse();
});

test('rejects a role that does not exist', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)->put(route('users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => ['Not A Real Role'],
    ]);

    $response->assertSessionHasErrors('roles.0');
});

test('an administrator disables a user', function () {
    Event::fake([UserDisabled::class]);

    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->from(route('users.edit', $user))
        ->put(route('users.status.update', $user), ['enabled' => false]);

    expect($user->refresh()->isDisabled())->toBeTrue();

    Event::assertDispatched(UserDisabled::class);
});

test('an administrator re-enables a disabled user', function () {
    $user = User::factory()->disabled()->create();

    $this->actingAs($this->admin)
        ->from(route('users.edit', $user))
        ->put(route('users.status.update', $user), ['enabled' => true]);

    expect($user->refresh()->isDisabled())->toBeFalse();
});

test('an administrator cannot disable their own account', function () {
    $response = $this->actingAs($this->admin)
        ->from(route('users.edit', $this->admin))
        ->put(route('users.status.update', $this->admin), ['enabled' => false]);

    $response->assertForbidden();

    expect($this->admin->refresh()->isDisabled())->toBeFalse();
});

describe('permission isolation', function () {
    test('a user without any platform permission cannot reach the user list', function () {
        $response = $this->actingAs(User::factory()->create())->get(route('users.index'));

        $response->assertForbidden();
    });

    test('view permission alone does not allow creating a user', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::UsersView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::UsersView->value);

        $this->actingAs($viewer)->get(route('users.index'))->assertOk();

        $this->actingAs($viewer)->post(route('users.store'), [
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertForbidden();
    });

    test('application permissions do not grant access to users', function () {
        $operator = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::ApplicationsManage->value, 'web');
        $operator->givePermissionTo(PlatformPermission::ApplicationsManage->value);

        $this->actingAs($operator)->get(route('users.index'))->assertForbidden();
    });

    test('a guest is redirected to the login screen', function () {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    });
});

test('the Super Admin role holds every platform permission', function () {
    $role = Role::findByName('Super Admin');

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(PlatformPermission::values());
});
