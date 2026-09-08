<?php

use App\Enums\ApplicationType;
use App\Enums\PlatformPermission;
use App\Events\ApplicationCreated;
use App\Events\ApplicationDisabled;
use App\Events\ClientSecretRegenerated;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
});

describe('filtering', function () {
    test('the listing narrows by status', function () {
        Application::factory()->create(['name' => 'Live']);
        Application::factory()->disabled()->create(['name' => 'Retired']);

        $this->actingAs($this->admin)
            ->get(route('applications.index', ['status' => 'disabled']))
            ->assertInertia(fn ($page) => $page
                ->has('applications.data', 1)
                ->where('applications.data.0.name', 'Retired'));
    });

    test('the listing narrows by each kind of client', function () {
        /*
         * The type is derived from the grant types and the presence of a
         * secret rather than stored, so the filter has to reproduce that
         * derivation in SQL. One case per type proves it does.
         */
        $manager = app(ApplicationManager::class);

        foreach (ApplicationType::cases() as $type) {
            $manager->create([
                'name' => 'A '.$type->value,
                'type' => $type,
                'redirect_uris' => $type->usesRedirectUris()
                    ? ['https://app.example.com/auth/callback']
                    : [],
            ]);
        }

        foreach (ApplicationType::cases() as $type) {
            $this->actingAs($this->admin)
                ->get(route('applications.index', ['type' => $type->value]))
                ->assertInertia(fn ($page) => $page
                    ->has('applications.data', 1)
                    ->where('applications.data.0.type', $type->value));
        }
    });
});

test('the listing says whether its row actions may be used', function () {
    Application::factory()->create();

    $viewer = User::factory()->create();
    Permission::findOrCreate(PlatformPermission::ApplicationsView->value, 'web');
    $viewer->givePermissionTo(PlatformPermission::ApplicationsView->value);

    /*
     * Registering is a page-level answer; editing is answered per row, because
     * a steward may edit what is assigned to them and register nothing.
     */
    $this->actingAs($this->admin)->get(route('applications.index'))
        ->assertInertia(fn ($page) => $page
            ->where('canAdminister', true)
            ->where('applications.data.0.can_manage', true));

    $this->actingAs($viewer)->get(route('applications.index'))
        ->assertInertia(fn ($page) => $page
            ->where('canAdminister', false)
            ->where('applications.data.0.can_manage', false));
});

test('an untouched post-logout list is accepted as empty', function () {
    /*
     * The URI fields always render one blank input, so an untouched list
     * arrives as [""]. Registering no post-logout URI is the documented
     * default, and has to remain possible.
     */
    $this->actingAs($this->admin)
        ->post(route('applications.store'), [
            'name' => 'Blank Lists',
            'type' => ApplicationType::Confidential->value,
            'redirect_uris' => ['https://app.example.com/auth/callback', ''],
            'post_logout_redirect_uris' => [''],
            'scopes' => ['openid'],
        ])
        ->assertSessionHasNoErrors();

    $application = Application::where('name', 'Blank Lists')->sole();

    expect($application->post_logout_redirect_uris)->toBe([])
        ->and($application->redirect_uris)->toBe(['https://app.example.com/auth/callback']);
});

test('applications can be found by name, word or client id', function () {
    $reporting = Application::factory()->create(['name' => 'Quarterly Reporting']);
    Application::factory()->create(['name' => 'Billing']);

    foreach (['Quarterly', 'Reporting', $reporting->id] as $term) {
        $this->actingAs($this->admin)->get(route('applications.index', ['search' => $term]))
            ->assertInertia(fn ($page) => $page
                ->has('applications.data', 1)
                ->where('applications.data.0.name', 'Quarterly Reporting'));
    }
});

test('the application list is rendered for an administrator', function () {
    app(ApplicationManager::class)->create([
        'name' => 'Customer Portal',
        'type' => ApplicationType::Confidential,
        'redirect_uris' => ['https://portal.example.com/auth/callback'],
    ]);

    $response = $this->actingAs($this->admin)->get(route('applications.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('applications/Index')
        ->has('applications.data', 1)
        ->where('applications.data.0.name', 'Customer Portal'));
});

describe('registering an application', function () {
    test('a confidential application is issued a hashed secret shown once', function () {
        Event::fake([ApplicationCreated::class]);

        $response = $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Customer Portal',
            'description' => 'The public customer area',
            'type' => 'confidential',
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
            'scopes' => ['openid', 'email'],
        ]);

        $application = Application::where('name', 'Customer Portal')->sole();

        $response->assertRedirect(route('applications.show', $application));
        $response->assertSessionHas('clientSecret');

        $plainSecret = session('clientSecret');

        expect($plainSecret)->toBeString()
            ->and($application->getRawOriginal('secret'))->not->toBe($plainSecret)
            ->and(Hash::check($plainSecret, $application->secret))->toBeTrue()
            ->and($application->redirect_uris)->toBe(['https://portal.example.com/auth/callback'])
            ->and($application->scopes)->toBe(['openid', 'email'])
            ->and($application->grant_types)->toBe(['authorization_code', 'refresh_token'])
            ->and($application->skips_authorization)->toBeFalse();

        Event::assertDispatched(ApplicationCreated::class);
    });

    test('the secret is shown once and then never again', function () {
        $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Customer Portal',
            'type' => 'confidential',
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
        ]);

        $application = Application::where('name', 'Customer Portal')->sole();

        /*
         * Captured before the request below, because reading the flash is what
         * ages it out of the session.
         */
        $secret = session('clientSecret');

        $this->actingAs($this->admin)
            ->get(route('applications.show', $application))
            ->assertInertia(fn ($page) => $page->where('clientSecret', $secret));

        $this->actingAs($this->admin)
            ->get(route('applications.show', $application))
            ->assertInertia(fn ($page) => $page->where('clientSecret', null));
    });

    test('a public application is created without a secret', function () {
        $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Mobile App',
            'type' => 'public',
            'redirect_uris' => ['https://mobile.example.com/auth/callback'],
        ]);

        $application = Application::where('name', 'Mobile App')->sole();

        expect($application->secret)->toBeNull()
            ->and($application->isConfidential())->toBeFalse();
    });

    test('a machine to machine application uses the client credentials grant', function () {
        $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Billing Worker',
            'type' => 'machine',
            'scopes' => ['openid'],
        ]);

        $application = Application::where('name', 'Billing Worker')->sole();

        expect($application->grant_types)->toBe(['client_credentials'])
            ->and($application->redirect_uris)->toBe([])
            ->and($application->isConfidential())->toBeTrue();
    });

    test('an interactive application requires at least one redirect uri', function () {
        $response = $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Customer Portal',
            'type' => 'confidential',
        ]);

        $response->assertSessionHasErrors('redirect_uris');
    });
});

describe('redirect uri validation', function () {
    test('rejects an unacceptable redirect uri', function (string $uri) {
        $response = $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Customer Portal',
            'type' => 'confidential',
            'redirect_uris' => [$uri],
        ]);

        $response->assertSessionHasErrors('redirect_uris.0');
    })->with([
        'wildcard host' => 'https://*.example.com/auth/callback',
        'wildcard path' => 'https://app.example.com/*',
        'plain http' => 'http://app.example.com/auth/callback',
        'relative path' => '/auth/callback',
        'missing host' => 'https://',
        'fragment' => 'https://app.example.com/auth/callback#token',
        'unsupported scheme' => 'ftp://app.example.com/auth/callback',
    ]);

    test('accepts an acceptable redirect uri', function (string $uri) {
        $response = $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Customer Portal '.md5($uri),
            'type' => 'confidential',
            'redirect_uris' => [$uri],
        ]);

        $response->assertSessionHasNoErrors();
    })->with([
        'https' => 'https://app.example.com/auth/callback',
        'https with port' => 'https://app.example.com:8443/auth/callback',
        'https with query' => 'https://app.example.com/auth/callback?tenant=acme',
        'localhost over http' => 'http://localhost:3000/auth/callback',
        'loopback ip over http' => 'http://127.0.0.1:3000/auth/callback',
    ]);

    test('rejects duplicate redirect uris', function () {
        $response = $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Customer Portal',
            'type' => 'confidential',
            'redirect_uris' => [
                'https://app.example.com/auth/callback',
                'https://app.example.com/auth/callback',
            ],
        ]);

        /*
         * Laravel's distinct rule attaches the error to each offending index,
         * which is what RedirectUriFields renders beside the field itself.
         */
        $response->assertSessionHasErrors(['redirect_uris.0', 'redirect_uris.1']);
    });

    test('an administrator adds and removes redirect uris', function () {
        $application = app(ApplicationManager::class)->create([
            'name' => 'Customer Portal',
            'type' => ApplicationType::Confidential,
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
        ]);

        $this->actingAs($this->admin)->put(route('applications.update', $application), [
            'name' => 'Customer Portal',
            'redirect_uris' => [
                'https://portal.example.com/auth/callback',
                'https://staging.example.com/auth/callback',
            ],
        ]);

        expect($application->refresh()->redirect_uris)->toBe([
            'https://portal.example.com/auth/callback',
            'https://staging.example.com/auth/callback',
        ]);

        $this->actingAs($this->admin)->put(route('applications.update', $application), [
            'name' => 'Customer Portal',
            'redirect_uris' => ['https://staging.example.com/auth/callback'],
        ]);

        expect($application->refresh()->redirect_uris)
            ->toBe(['https://staging.example.com/auth/callback']);
    });
});

describe('scopes', function () {
    test('the scopes offered are exactly those discovery advertises', function () {
        /*
         * Other packages register their own scopes with Passport, so the
         * administration interface must not read the list from there.
         */
        $advertised = $this->getJson('/.well-known/openid-configuration')
            ->json('scopes_supported');

        $this->actingAs($this->admin)
            ->get(route('applications.create'))
            ->assertInertia(fn ($page) => $page->where(
                'availableScopes',
                fn ($scopes) => collect($scopes)->pluck('id')->all() === $advertised,
            ));
    });

    test('rejects a scope Passport knows but this server does not advertise', function () {
        Passport::tokensCan([
            ...collect(config('oidc-server.scopes'))->map(fn ($scope) => $scope['description'])->all(),
            'mcp:use' => 'Use MCP server',
        ]);

        $response = $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Customer Portal',
            'type' => 'confidential',
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
            'scopes' => ['mcp:use'],
        ]);

        $response->assertSessionHasErrors('scopes.0');
    });

    test('rejects a scope this server does not define', function () {
        $response = $this->actingAs($this->admin)->post(route('applications.store'), [
            'name' => 'Customer Portal',
            'type' => 'confidential',
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
            'scopes' => ['not-a-real-scope'],
        ]);

        $response->assertSessionHasErrors('scopes.0');
    });

    test('an administrator changes the scopes an application may request', function () {
        $application = app(ApplicationManager::class)->create([
            'name' => 'Reporting',
            'type' => ApplicationType::Confidential,
            'redirect_uris' => ['https://reporting.example.com/auth/callback'],
            'scopes' => ['openid'],
        ]);

        $this->actingAs($this->admin)->put(route('applications.update', $application), [
            'name' => 'Reporting',
            'redirect_uris' => ['https://reporting.example.com/auth/callback'],
            'scopes' => ['openid', 'profile', 'email'],
        ]);

        expect($application->refresh()->scopes)->toBe(['openid', 'profile', 'email']);
    });
});

describe('client secret', function () {
    beforeEach(function () {
        $this->application = app(ApplicationManager::class)->create([
            'name' => 'Customer Portal',
            'type' => ApplicationType::Confidential,
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
        ]);
    });

    test('regenerating replaces the previous secret', function () {
        Event::fake([ClientSecretRegenerated::class]);

        $previous = $this->application->getRawOriginal('secret');

        $response = $this->actingAs($this->admin)
            ->put(route('applications.secret.update', $this->application));

        $response->assertRedirect(route('applications.show', $this->application));

        $newSecret = session('clientSecret');

        expect($this->application->refresh()->getRawOriginal('secret'))->not->toBe($previous)
            ->and(Hash::check($newSecret, $this->application->secret))->toBeTrue();

        Event::assertDispatched(ClientSecretRegenerated::class);
    });

    test('the previous secret stops authenticating the client', function () {
        $original = $this->application->plainSecret;

        $this->actingAs($this->admin)
            ->put(route('applications.secret.update', $this->application));

        expect(Hash::check($original, $this->application->refresh()->secret))->toBeFalse();
    });

    test('a public client has no secret to regenerate', function () {
        $public = app(ApplicationManager::class)->create([
            'name' => 'Mobile App',
            'type' => ApplicationType::Public,
            'redirect_uris' => ['https://mobile.example.com/auth/callback'],
        ]);

        $this->actingAs($this->admin)
            ->put(route('applications.secret.update', $public))
            ->assertForbidden();
    });

    test('the secret is never present in a list or detail payload', function () {
        $this->actingAs($this->admin)
            ->get(route('applications.index'))
            ->assertInertia(fn ($page) => $page->missing('applications.data.0.secret'));

        $this->actingAs($this->admin)
            ->get(route('applications.edit', $this->application))
            ->assertInertia(fn ($page) => $page->missing('application.secret'));
    });
});

describe('disabling', function () {
    test('disabling an application revokes the tokens it holds', function () {
        Event::fake([ApplicationDisabled::class]);

        $application = app(ApplicationManager::class)->create([
            'name' => 'Customer Portal',
            'type' => ApplicationType::Confidential,
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
        ]);

        $token = $application->tokens()->create([
            'id' => 'token-under-test',
            'user_id' => $this->admin->id,
            'scopes' => ['openid'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($this->admin)
            ->from(route('applications.show', $application))
            ->put(route('applications.status.update', $application), ['enabled' => false]);

        expect($application->refresh()->isEnabled())->toBeFalse()
            ->and($token->refresh()->revoked)->toBeTrue();

        Event::assertDispatched(ApplicationDisabled::class);
    });

    test('a disabled application cannot obtain a token', function () {
        $application = app(ApplicationManager::class)->create([
            'name' => 'Customer Portal',
            'type' => ApplicationType::Machine,
        ]);

        $secret = $application->plainSecret;

        $this->actingAs($this->admin)
            ->from(route('applications.show', $application))
            ->put(route('applications.status.update', $application), ['enabled' => false]);

        $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $application->id,
            'client_secret' => $secret,
        ])->assertUnauthorized();
    });

    test('an administrator returns a disabled application to service', function () {
        $application = app(ApplicationManager::class)->create([
            'name' => 'Customer Portal',
            'type' => ApplicationType::Confidential,
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
        ]);

        app(ApplicationManager::class)->disable($application);

        $this->actingAs($this->admin)
            ->from(route('applications.show', $application))
            ->put(route('applications.status.update', $application), ['enabled' => true]);

        expect($application->refresh()->isEnabled())->toBeTrue();
    });
});

test('the application type cannot be changed after creation', function () {
    $application = app(ApplicationManager::class)->create([
        'name' => 'Customer Portal',
        'type' => ApplicationType::Confidential,
        'redirect_uris' => ['https://portal.example.com/auth/callback'],
    ]);

    $this->actingAs($this->admin)->put(route('applications.update', $application), [
        'name' => 'Customer Portal',
        'type' => 'public',
        'redirect_uris' => ['https://portal.example.com/auth/callback'],
    ]);

    expect($application->refresh()->grant_types)->toBe(['authorization_code', 'refresh_token'])
        ->and($application->isConfidential())->toBeTrue();
});

describe('permission isolation', function () {
    test('a user without any platform permission cannot list applications', function () {
        assertPageRefused(
            $this->actingAs(User::factory()->create())->get(route('applications.index'))
        );
    });

    test('view permission alone does not allow registering an application', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::ApplicationsView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::ApplicationsView->value);

        $this->actingAs($viewer)->get(route('applications.index'))->assertOk();

        $this->actingAs($viewer)->post(route('applications.store'), [
            'name' => 'Customer Portal',
            'type' => 'confidential',
            'redirect_uris' => ['https://portal.example.com/auth/callback'],
        ])->assertForbidden();
    });

    test('user permissions do not grant access to applications', function () {
        $operator = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::UsersManage->value, 'web');
        $operator->givePermissionTo(PlatformPermission::UsersManage->value);

        assertPageRefused($this->actingAs($operator)->get(route('applications.index')));
    });

    test('a guest is redirected to the login screen', function () {
        $this->get(route('applications.index'))->assertRedirect(route('login'));
    });
});

test('an administrator created application maps onto a Passport client', function () {
    $application = app(ApplicationManager::class)->create([
        'name' => 'Customer Portal',
        'type' => ApplicationType::Confidential,
        'redirect_uris' => ['https://portal.example.com/auth/callback'],
    ]);

    $found = app(ClientRepository::class)->findActive($application->id);

    expect($found)->toBeInstanceOf(Application::class)
        ->and($found->id)->toBe($application->id);
});
