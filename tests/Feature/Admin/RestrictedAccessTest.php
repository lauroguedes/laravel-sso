<?php

use App\Enums\ApplicationType;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationManager;
use Illuminate\Support\Facades\Route;
use Inertia\Support\SessionKey;

beforeEach(function () {
    $this->member = User::factory()->create(['name' => 'Ordinary Member']);
});

test('someone with no administrative permission sees only their own dashboard', function () {
    /*
     * The operator dashboard counts every user and lists recent security
     * events, neither of which is theirs to read.
     */
    $this->actingAs($this->member)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/Personal')
            ->where('user.name', 'Ordinary Member')
            ->missing('counts')
            ->missing('recent'));
});

test('their dashboard lists what they hold in each application', function () {
    $application = app(ApplicationManager::class)->create([
        'name' => 'Reporting',
        'type' => ApplicationType::Confidential,
        'redirect_uris' => ['https://app.example.com/auth/callback'],
    ]);

    $role = $application->roles()->create(['name' => 'Analyst']);
    $role->permissions()->attach(
        $application->permissions()->create(['name' => 'reports.view'])->id
    );

    $application->grantAccessTo($this->member, $role);

    $this->actingAs($this->member)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('access.0.application', 'Reporting')
            ->where('access.0.role', 'Analyst')
            ->where('access.0.permissions.0', 'reports.view'));
});

test('their dashboard describes nobody else', function () {
    $other = User::factory()->create(['name' => 'Somebody Else']);
    $application = Application::factory()->create();
    $application->grantAccessTo($other);

    $response = $this->actingAs($this->member)->get(route('dashboard'));

    expect(json_encode($response->viewData('page')['props']))
        ->not->toContain('Somebody Else')
        ->and(json_encode($response->viewData('page')['props']))
        ->not->toContain($other->email);
});

test('an administrator still gets the operational dashboard', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('Dashboard')->has('counts'));
});

describe('the interface offers only what it can open', function () {
    test('a member is told they may reach no administrative section', function () {
        $this->actingAs($this->member)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('can.viewApplications', false)
                ->where('can.viewUsers', false)
                ->where('can.viewAudit', false));
    });

    test('an administrator is told they may reach all of them', function () {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('can.viewApplications', true)
                ->where('can.viewUsers', true)
                ->where('can.viewAudit', true));
    });
});

describe('pages that cannot be opened', function () {
    test('a refused page sends the reader to the dashboard with an explanation', function () {
        assertPageRefused(
            $this->actingAs($this->member)->get(route('users.index'))
        );
    });

    test('a page that does not exist does the same', function () {
        assertPageRefused(
            $this->actingAs($this->member)->get('/no-such-page'),
            'does not exist',
        );
    });

    test('the protocol endpoints keep their status codes', function () {
        /*
         * A relying party is written against the status code, so the interface
         * behaviour must not reach the endpoints it integrates with.
         */
        $this->getJson('/oauth/userinfo')->assertUnauthorized();

        /*
         * And with a browser's own headers, so the exclusion is the path
         * rather than the Accept header the caller happened to send.
         */
        $this->get('/oauth/nothing-here')->assertNotFound();
    });

    test('a server fault is the stronger colour', function () {
        /*
         * A 4xx is something about the request — a wrong address, a page that
         * is not theirs. A 5xx is this server failing, which is not the
         * reader's doing and reads as an error rather than a warning.
         */
        config()->set('app.debug', false);

        Route::middleware('web')->get('/probe-server-fault', function (): void {
            throw new RuntimeException('Something broke.');
        });

        $response = $this->actingAs($this->member)->get('/probe-server-fault');

        $response->assertRedirect(route('dashboard'));

        expect(session(SessionKey::FLASH_DATA)['toast']['type'])->toBe('error')
            ->and(session(SessionKey::FLASH_DATA)['toast']['message'])
            ->toContain('went wrong');
    });

    test('a server fault keeps its exception page while debugging', function () {
        /*
         * That page is the reason debugging is on; swapping it for a sentence
         * would hide the stack trace a developer is waiting for.
         */
        config()->set('app.debug', true);

        Route::middleware('web')->get('/probe-server-fault', function (): void {
            throw new RuntimeException('Something broke.');
        });

        $this->actingAs($this->member)
            ->get('/probe-server-fault')
            ->assertStatus(500);
    });

    test('a refused form submission keeps its status', function () {
        /*
         * The interface hides controls the reader may not use, so one arriving
         * is a client acting out of turn. Answering with a redirect would tell
         * it the request had been handled.
         */
        $this->actingAs($this->member)
            ->post(route('users.store'), [
                'name' => 'Nobody',
                'email' => 'nobody@example.com',
                'password' => 'correct-horse-battery-staple',
                'password_confirmation' => 'correct-horse-battery-staple',
            ])
            ->assertForbidden();

        expect(User::where('email', 'nobody@example.com')->exists())->toBeFalse();
    });
});
