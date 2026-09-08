<?php

use App\Enums\PlatformRole;
use App\Models\Application;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->developer = User::factory()->developer()->create();
    $this->application = Application::factory()->create(['name' => 'Reporting']);
    $this->other = Application::factory()->create(['name' => 'Somebody Elses']);

    $this->application->managers()->attach($this->developer);
});

describe('an application they look after', function () {
    test('they can open and configure', function () {
        $this->actingAs($this->developer)
            ->get(route('applications.show', $this->application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManage', true));

        $this->actingAs($this->developer)
            ->put(route('applications.update', $this->application), [
                'name' => 'Reporting Renamed',
                'description' => 'Now with a new name.',
                'redirect_uris' => ['https://reporting.example.com/callback'],
                'post_logout_redirect_uris' => [],
                'scopes' => ['openid'],
            ])->assertSessionHasNoErrors();

        expect($this->application->refresh()->name)->toBe('Reporting Renamed');
    });

    test('they can rotate its client secret', function () {
        $this->actingAs($this->developer)
            ->put(route('applications.secret.update', $this->application))
            ->assertSessionHasNoErrors();
    });

    test('they can define the roles its tokens carry', function () {
        $this->actingAs($this->developer)
            ->post(route('applications.roles.store', $this->application), ['name' => 'Analyst'])
            ->assertSessionHasNoErrors();

        expect($this->application->roles()->where('name', 'Analyst')->exists())->toBeTrue();
    });

    test('they can read its history, without who did it or from where', function () {
        /*
         * The trail tells them what happened to their application. Which
         * administrator acted, and from which address, is a roll of people —
         * withheld for the same reason the access list is.
         */
        $this->actingAs($this->admin)
            ->put(route('applications.status.update', $this->application), ['enabled' => false]);

        $this->actingAs($this->developer)
            ->get(route('applications.audit', $this->application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('entries.data.0.causer', null)
                ->where('entries.data.0.ip_address', null)
                ->whereNot('entries.data.0.label', null));

        $this->actingAs($this->admin)
            ->get(route('applications.audit', $this->application))
            ->assertInertia(fn ($page) => $page->whereNot('entries.data.0.causer', null));
    });
});

describe('an application they do not look after', function () {
    test('is not in their listing', function () {
        $this->actingAs($this->developer)->get(route('applications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('applications.data', 1)
                ->where('applications.data.0.name', 'Reporting'));
    });

    test('cannot be opened by naming it', function () {
        assertPageRefused(
            $this->actingAs($this->developer)->get(route('applications.show', $this->other))
        );
    });

    test('cannot be edited by naming it', function () {
        $this->actingAs($this->developer)
            ->put(route('applications.update', $this->other), [
                'name' => 'Taken Over',
                'redirect_uris' => ['https://elsewhere.example.com/callback'],
                'post_logout_redirect_uris' => [],
                'scopes' => ['openid'],
            ])->assertForbidden();

        expect($this->other->refresh()->name)->toBe('Somebody Elses');
    });

    test('does not leak through its audit trail', function () {
        assertPageRefused(
            $this->actingAs($this->developer)->get(route('applications.audit', $this->other))
        );
    });
});

describe('what stays with administrators', function () {
    test('registering an application', function () {
        assertPageRefused(
            $this->actingAs($this->developer)->get(route('applications.create'))
        );

        $this->actingAs($this->developer)
            ->post(route('applications.store'), ['name' => 'Mine Now', 'type' => 'confidential'])
            ->assertForbidden();
    });

    test('enabling and disabling, so a steward cannot undo being stopped', function () {
        $this->actingAs($this->developer)
            ->put(route('applications.status.update', $this->application), ['enabled' => false])
            ->assertForbidden();

        expect($this->application->refresh()->isEnabled())->toBeTrue();
    });

    test('deciding who may sign in — including through the edit form', function () {
        /*
         * The access page refuses them, so the edit form must not be a way
         * around it. Both fields decide who reaches the application rather
         * than how it is configured: one turns off the grants list entirely,
         * the other turns off the consent screen. Omitting them used to read
         * as "off", which made not mentioning them the exploit.
         */
        $this->application->forceFill([
            'restricts_access' => true,
            'skips_authorization' => false,
        ])->save();

        $this->actingAs($this->developer)
            ->put(route('applications.update', $this->application), [
                'name' => 'Reporting',
                'redirect_uris' => ['https://reporting.example.com/callback'],
                'post_logout_redirect_uris' => [],
                'scopes' => ['openid'],
                'skips_authorization' => true,
            ])->assertSessionHasNoErrors();

        $this->application->refresh();

        expect($this->application->restricts_access)->toBeTrue()
            ->and($this->application->skips_authorization)->toBeFalse();

        $stranger = User::factory()->create();
        expect($this->application->admits($stranger))->toBeFalse();
    });

    test('an administrator still decides both', function () {
        $this->actingAs($this->admin)
            ->put(route('applications.update', $this->application), [
                'name' => 'Reporting',
                'redirect_uris' => ['https://reporting.example.com/callback'],
                'post_logout_redirect_uris' => [],
                'scopes' => ['openid'],
                'skips_authorization' => true,
                'restricts_access' => true,
            ])->assertSessionHasNoErrors();

        $this->application->refresh();

        expect($this->application->skips_authorization)->toBeTrue()
            ->and($this->application->restricts_access)->toBeTrue();
    });

    test('revoking every token the application holds', function () {
        /*
         * Service-affecting, and next door to enabling and disabling, which a
         * steward is deliberately not given.
         */
        $this->actingAs($this->developer)
            ->delete(route('applications.tokens.destroy', $this->application))
            ->assertForbidden();
    });

    test('deciding who may sign in', function () {
        assertPageRefused(
            $this->actingAs($this->developer)->get(route('applications.grants.index', $this->application))
        );

        $this->actingAs($this->developer)
            ->post(route('applications.grants.store', $this->application), [
                'user_id' => $this->developer->id,
                'application_role_id' => null,
            ])->assertForbidden();
    });

    test('choosing who looks after it', function () {
        assertPageRefused(
            $this->actingAs($this->developer)->get(route('applications.managers.index', $this->application))
        );

        $accomplice = User::factory()->developer()->create();

        $this->actingAs($this->developer)
            ->post(route('applications.managers.store', $this->application), ['user_id' => $accomplice->id])
            ->assertForbidden();

        expect($this->application->managers()->count())->toBe(1);
    });

    test('the rest of the server', function () {
        assertPageRefused($this->actingAs($this->developer)->get(route('users.index')));
        assertPageRefused($this->actingAs($this->developer)->get(route('audit.index')));
        assertPageRefused($this->actingAs($this->developer)->get(route('application-settings.edit')));
    });
});

describe('the assignment', function () {
    test('grants nothing once the Developer role is withdrawn', function () {
        /*
         * The point of checking the permission alongside the row: withdrawing
         * the role has to make every application they were given go quiet at
         * once, without unpicking the assignments one by one.
         */
        $this->developer->removeRole(PlatformRole::Developer->value);

        assertPageRefused(
            $this->actingAs($this->developer->fresh())->get(route('applications.show', $this->application))
        );
    });

    test('is refused for an administrator, who already reaches everything', function () {
        $this->actingAs($this->admin)
            ->post(route('applications.managers.store', $this->other), ['user_id' => $this->admin->id])
            ->assertSessionHasErrors('user_id');

        expect($this->other->managers()->count())->toBe(0);
    });

    test('is refused for somebody who does not hold the role', function () {
        $ordinary = User::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('applications.managers.store', $this->other), ['user_id' => $ordinary->id])
            ->assertSessionHasErrors('user_id');

        expect($this->other->managers()->count())->toBe(0);
    });

    test('an administrator makes and unmakes', function () {
        $this->actingAs($this->admin)
            ->post(route('applications.managers.store', $this->other), ['user_id' => $this->developer->id])
            ->assertSessionHasNoErrors();

        expect($this->other->managers()->count())->toBe(1);

        $this->actingAs($this->admin)
            ->delete(route('applications.managers.destroy', [$this->other, $this->developer]));

        expect($this->other->refresh()->managers()->count())->toBe(0);
    });
});

describe('the interface offers only what they can open', function () {
    test('their dashboard names the applications they look after', function () {
        $this->actingAs($this->developer)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/Personal')
                ->where('stewardship.0.name', 'Reporting')
                ->missing('counts'));
    });

    test('the section rail leaves out what they may not reach', function () {
        $this->actingAs($this->developer)->get(route('applications.show', $this->application))
            ->assertInertia(fn ($page) => $page
                ->where('sections', [
                    ['key' => 'overview', 'title' => 'Overview'],
                    ['key' => 'roles', 'title' => 'Roles'],
                    ['key' => 'audit', 'title' => 'Audit'],
                ]));
    });

    test('an administrator is offered every section', function () {
        $this->actingAs($this->admin)->get(route('applications.show', $this->application))
            ->assertInertia(fn ($page) => $page->has('sections', 5));
    });
});
