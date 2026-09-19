<?php

use App\Enums\AuditEvent;
use App\Enums\PlatformPermission;
use App\Models\Application;
use App\Models\User;
use App\Services\AuditLogger;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->application = Application::factory()->create(['name' => 'Reporting']);
    $this->audit = app(AuditLogger::class);
});

test('the audit page lists entries newest first', function () {
    $this->audit->record(AuditEvent::ApplicationCreated, $this->application, $this->application);
    $this->audit->record(AuditEvent::UserLoginFailed);

    $response = $this->actingAs($this->admin)->get(route('audit.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('audit/Index')
        ->has('entries.data', 2)
        ->where('entries.data.0.event', AuditEvent::UserLoginFailed->value));
});

test('the listing carries what the pager needs', function () {
    /*
     * The pager offers pages by number, which needs the count and the last
     * page that only a length-aware paginator carries. The trail was simple
     * paginated before, and the pager had nothing to number.
     */
    $this->audit->record(AuditEvent::UserLoggedIn);

    $this->actingAs($this->admin)->get(route('audit.index'))
        ->assertInertia(fn ($page) => $page
            ->has('entries.total')
            ->has('entries.last_page')
            ->has('entries.current_page')
            ->has('entries.from')
            ->has('entries.to'));
});

test('entries can be narrowed to one stream', function () {
    $this->audit->record(AuditEvent::ApplicationCreated, $this->application, $this->application);
    $this->audit->record(AuditEvent::UserLoginFailed);

    $this->actingAs($this->admin)
        ->get(route('audit.index', ['stream' => 'security']))
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.stream', 'security'));
});

test('entries can be searched by event', function () {
    $this->audit->record(AuditEvent::ApplicationCreated, $this->application, $this->application);
    $this->audit->record(AuditEvent::UserLoginFailed);

    $this->actingAs($this->admin)
        ->get(route('audit.index', ['search' => 'LOGIN']))
        ->assertInertia(fn ($page) => $page->has('entries.data', 1));
});

test('an application page shows only its own history', function () {
    $billing = Application::factory()->create(['name' => 'Billing']);

    $this->audit->record(AuditEvent::ApplicationUpdated, $this->application, $this->application);
    $this->audit->record(AuditEvent::ApplicationUpdated, $billing, $billing);

    $this->actingAs($this->admin)
        ->get(route('applications.audit', $this->application))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('applications/Audit')
            ->has('entries.data', 1)
            ->where('application.name', 'Reporting'));
});

test('the trail cannot be edited or deleted through the interface', function () {
    $this->audit->record(AuditEvent::UserLoggedIn);

    /*
     * Only a read route exists. Retention is the activitylog:clean command's
     * job, not something an administrator can reach.
     */
    $routes = collect(app('router')->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'audit.'))
        ->flatMap(fn ($route): array => $route->methods())
        ->unique()
        ->values();

    expect($routes->all())->toEqualCanonicalizing(['GET', 'HEAD']);
});

describe('permission isolation', function () {
    test('reading the trail needs the audit permission', function () {
        $manager = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::UsersManage->value, 'web');
        $manager->givePermissionTo(PlatformPermission::UsersManage->value);

        assertPageRefused($this->actingAs($manager)->get(route('audit.index')));
    });

    test('the audit permission alone is enough to read it', function () {
        $auditor = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::AuditView->value, 'web');
        $auditor->givePermissionTo(PlatformPermission::AuditView->value);

        $this->actingAs($auditor)->get(route('audit.index'))->assertOk();
    });

    test('an application audit needs permission to see that application too', function () {
        $auditor = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::AuditView->value, 'web');
        $auditor->givePermissionTo(PlatformPermission::AuditView->value);

        assertPageRefused(
            $this->actingAs($auditor)->get(route('applications.audit', $this->application))
        );
    });
});

describe('dashboard', function () {
    test('it counts what is currently in service', function () {
        Application::factory()->create();
        Application::factory()->disabled()->create();
        User::factory()->disabled()->create();

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.applications', 2)
                ->where('counts.disabled_users', 1));
    });

    test('recent activity is withheld without permission to read the trail', function () {
        $this->audit->record(AuditEvent::UserLoggedIn);

        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::UsersView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::UsersView->value);

        $this->actingAs($viewer)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('recent', null));
    });

    test('recent activity is shown to an administrator who may read it', function () {
        $this->audit->record(AuditEvent::UserLoggedIn);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->has('recent.security', 1));
    });
});
