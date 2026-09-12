<?php

use App\Enums\PlatformPermission;
use App\Models\Setting;
use App\Models\User;
use App\Providers\SettingsServiceProvider;
use App\Services\InterfaceOptions;
use App\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
});

/**
 * Save one section of the settings, as its tab does.
 */
function saveSection(string $section, array $values = []): TestResponse
{
    return test()->actingAs(test()->admin)
        ->post(route('application-settings.update'), [
            'section' => $section,
            ...sectionDefaults()[$section],
            ...$values,
        ]);
}

/**
 * A complete, unchanged payload per section, to vary one field of.
 *
 * @return array<string, array<string, mixed>>
 */
function sectionDefaults(): array
{
    return [
        'brand' => ['brand_name' => 'Laravel SSO'],
        'appearance' => ['base_color' => 'neutral', 'accent' => 'default'],
        'layout' => [
            'rows_per_page' => 15,
            'sidebar_variant' => 'inset',
            'auth_layout' => 'split',
        ],
        'links' => ['documentation_links' => []],
        'access' => [
            /*
             * A switch that posts nothing is off, so an unchanged payload has
             * to say what each one currently is.
             */
            'allow_registration' => '0',
            'require_email_verification' => '1',
            'logout_other_sessions_on_password_change' => '1',
            'access_token_ttl' => 900,
            'refresh_token_ttl' => 1209600,
            'id_token_ttl' => 900,
            'session_lifetime' => 120,
            'audit_retention_days' => 365,
        ],
    ];
}

/**
 * Read the settings back, as the next request would.
 */
function storedSettings(): Settings
{
    $settings = app(Settings::class);
    $settings->flush();

    return $settings;
}

test('an administrator changes how the server presents itself', function () {
    saveSection('brand', ['brand_name' => 'Acme Identity'])
        ->assertRedirect(route('application-settings.edit', ['tab' => 'brand']));

    /* Both away from their defaults, or the assertion proves nothing. */
    saveSection('layout', ['rows_per_page' => 50, 'auth_layout' => 'card']);

    expect(storedSettings()->get('brand_name'))->toBe('Acme Identity')
        ->and(storedSettings()->get('rows_per_page'))->toBe(50)
        ->and(storedSettings()->get('auth_layout'))->toBe('card');
});

test('saving one section leaves the others alone', function () {
    /*
     * Each tab posts only its own fields. If the controller wrote the whole
     * set, saving the links would quietly reset the brand to whatever the form
     * last rendered.
     */
    saveSection('brand', ['brand_name' => 'Acme Identity']);
    saveSection('links', ['documentation_links' => [['label' => 'Runbook', 'url' => 'https://wiki.example.com/sso']]]);

    /* toEqual: MySQL's JSON column reorders an object's keys. */
    expect(storedSettings()->get('brand_name'))->toBe('Acme Identity')
        ->and(storedSettings()->get('documentation_links'))
        ->toEqual([['label' => 'Runbook', 'url' => 'https://wiki.example.com/sso']]);
});

test('a field belonging to another section is ignored', function () {
    saveSection('links', ['brand_name' => 'Smuggled In']);

    expect(storedSettings()->get('brand_name'))->toBe('Laravel SSO');
});

test('an unknown section is refused', function () {
    $this->actingAs($this->admin)
        ->post(route('application-settings.update'), ['section' => 'everything'])
        ->assertSessionHasErrors('section');
});

test('the page size is stored as a number, not the string a form posts', function () {
    /*
     * It is compared strictly, both against the sizes on offer and against
     * the default that decides whether a row is stored at all, so a string
     * would validate and then silently do nothing.
     */
    saveSection('layout', ['rows_per_page' => '50']);

    expect(storedSettings()->get('rows_per_page'))->toBe(50);
});

test('the chosen page size is what a listing shows', function () {
    User::factory()->count(30)->create();

    saveSection('layout', ['rows_per_page' => 25]);

    $this->actingAs($this->admin)->get(route('users.index'))
        ->assertInertia(fn ($page) => $page->has('users.data', 25));
});

test('a value left at its default is not stored', function () {
    /*
     * The table records decisions, not a copy of the configuration, so
     * changing a default later still reaches installs that never touched it.
     */
    saveSection('layout');

    expect(Setting::query()->whereKey('auth_layout')->exists())->toBeFalse();
});

test('the brand reaches every page', function () {
    saveSection('brand', ['brand_name' => 'Acme Identity']);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('branding.name', 'Acme Identity')
            ->where('branding.sidebarVariant', 'inset'));
});

test('the chosen name is the name the server sends mail under', function () {
    /*
     * Every notification reads config('app.name') for its greeting and
     * signature, and none of them knows this settings table exists.
     */
    saveSection('brand', ['brand_name' => 'Acme Identity']);

    storedSettings();
    config()->set('app.name', 'Laravel');

    /*
     * Boot the provider again, as a queue worker's container would. Rebuilding
     * the whole application here would drop the test's transaction with the
     * row still in it.
     */
    (new SettingsServiceProvider($this->app))->boot();

    expect(config('app.name'))->toBe('Acme Identity');
});

describe('settings pinned through the environment', function () {
    test('are what the interface shows', function () {
        config()->set('sso.defaults.brand_name', 'Pinned Name');

        expect(storedSettings()->defaults()['brand_name'])->toBe('Pinned Name');
    });

    test('cannot be edited away', function () {
        /*
         * The form disables the field, but the request is what has to hold:
         * saving an edit that the next deploy silently reverts is worse than
         * refusing it.
         */
        config()->set('sso.pinned', ['brand_name']);

        saveSection('brand', ['brand_name' => 'Acme Identity']);

        expect(Setting::query()->whereKey('brand_name')->exists())->toBeFalse();
    });

    test('are named on the page, so an administrator knows why', function () {
        config()->set('sso.pinned', ['brand_name']);

        $this->actingAs($this->admin)->get(route('application-settings.edit'))
            ->assertInertia(fn ($page) => $page->where('pinned', ['brand_name']));
    });
});

describe('the open tab', function () {
    test('is the one that was saved, not the first', function () {
        /*
         * Each section saves with a redirect. Without the tab in the URL the
         * answer to "saved" would be the reader thrown back to Brand, and a
         * full reload would have nothing to remember by at all.
         */
        saveSection('layout')->assertRedirect(
            route('application-settings.edit', ['tab' => 'layout'])
        );

        $this->actingAs($this->admin)
            ->get(route('application-settings.edit', ['tab' => 'layout']))
            ->assertInertia(fn ($page) => $page->where('tab', 'layout'));
    });

    test('falls back to the first when the URL names one that does not exist', function () {
        $this->actingAs($this->admin)
            ->get(route('application-settings.edit', ['tab' => 'nonsense']))
            ->assertInertia(fn ($page) => $page->where('tab', 'brand'));
    });
});

describe('the palette', function () {
    test('reaches the page as custom properties', function () {
        saveSection('appearance', ['base_color' => 'slate', 'accent' => 'blue']);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        /*
         * Rendered into the document rather than swapped in by the client, so
         * the first paint is already the right colour.
         */
        $response->assertSee('--background:hsl(0 0% 100%)', false)
            ->assertSee('--primary:hsl(221.2 83.2% 53.3%)', false);
    });

    test('adds nothing when nobody changed a colour', function () {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertDontSee('--primary:hsl', false);
    });

    test('is applied by leaving the single-page navigation', function () {
        /*
         * The stylesheet lives in the document head, which only a full page
         * load replaces. Answering an appearance save with an ordinary Inertia
         * redirect would tell an administrator their colour was saved while
         * they were still looking at the old one.
         */
        $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->post(route('application-settings.update'), [
                'section' => 'appearance',
                'base_color' => 'slate',
                'accent' => 'blue',
            ])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('application-settings.edit', ['tab' => 'appearance']));
    });

    test('every other section stays within it', function () {
        $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->post(route('application-settings.update'), [
                'section' => 'brand',
                'brand_name' => 'Acme Identity',
            ])
            ->assertRedirect(route('application-settings.edit', ['tab' => 'brand']));
    });

    test('a palette that does not exist is refused', function () {
        saveSection('appearance', ['base_color' => 'chartreuse'])
            ->assertSessionHasErrors('base_color');
    });
});

describe('access and security', function () {
    test('turning registration off removes the page rather than hiding it', function () {
        saveSection('access', ['allow_registration' => '1']);

        expect(storedSettings()->get('allow_registration'))->toBeTrue();

        $this->app->forgetInstance(Settings::class);
        (new SettingsServiceProvider($this->app))->register();

        expect(Features::enabled(Features::registration()))->toBeTrue();
    });

    test('the session lifetime an administrator chose is the one in force', function () {
        saveSection('access', ['session_lifetime' => 30]);

        $this->app->forgetInstance(Settings::class);
        (new SettingsServiceProvider($this->app))->register();

        expect(config('session.lifetime'))->toBe(30);
    });

    test('a token lifetime an administrator chose is the one tokens are issued with', function () {
        /*
         * The issuer reads "oidc-server.tokens". Storing the value under this
         * project's own mirror of it would save cleanly and change nothing,
         * which is the one failure an operator has no way to see.
         */
        saveSection('access', ['access_token_ttl' => 300]);

        $this->app->forgetInstance(Settings::class);
        (new SettingsServiceProvider($this->app))->register();

        expect(config('oidc-server.tokens.access_token_ttl'))->toBe(300);
    });

    test('a token lifetime outside the bounds is refused', function () {
        /*
         * The bound is InterfaceOptions', so the field that offers it and the
         * rule that rejects it cannot disagree about what is acceptable.
         */
        $bounds = collect(app(InterfaceOptions::class)->durations())
            ->firstWhere('name', 'access_token_ttl');

        saveSection('access', ['access_token_ttl' => $bounds['min'] - 1])
            ->assertSessionHasErrors('access_token_ttl');

        saveSection('access', ['access_token_ttl' => $bounds['max'] + 1])
            ->assertSessionHasErrors('access_token_ttl');

        saveSection('access', ['access_token_ttl' => $bounds['min']])
            ->assertSessionHasNoErrors();
    });

    test('the audit retention an administrator chose is what the clean-up uses', function () {
        saveSection('access', ['audit_retention_days' => 90]);

        $this->app->forgetInstance(Settings::class);
        (new SettingsServiceProvider($this->app))->register();

        expect(config('activitylog.clean_after_days'))->toBe(90);
    });
});

describe('images', function () {
    test('a logo is stored where the sign-in page can read it', function () {
        Storage::fake('public');

        saveSection('brand', ['logo' => UploadedFile::fake()->image('logo.png', 64, 64)]);

        $path = storedSettings()->get('brand_logo');

        expect($path)->toBeString();
        Storage::disk('public')->assertExists($path);
    });

    test('replacing one removes the file it replaced', function () {
        Storage::fake('public');

        saveSection('brand', ['logo' => UploadedFile::fake()->image('first.png')]);
        $first = storedSettings()->get('brand_logo');

        saveSection('brand', ['logo' => UploadedFile::fake()->image('second.png')]);

        expect(storedSettings()->get('brand_logo'))->not->toBe($first);
        Storage::disk('public')->assertMissing($first);
    });

    test('something that is not an image is refused', function () {
        Storage::fake('public');

        saveSection('brand', ['logo' => UploadedFile::fake()->create('payload.php', 8, 'text/php')])
            ->assertSessionHasErrors('logo');
    });

    test('saving a section that carries no upload leaves the logo alone', function () {
        Storage::fake('public');

        saveSection('brand', ['logo' => UploadedFile::fake()->image('logo.png')]);
        $path = storedSettings()->get('brand_logo');

        saveSection('links');

        expect(storedSettings()->get('brand_logo'))->toBe($path);
    });

    test('the sign-in background reaches the page that shows it', function () {
        Storage::fake('public');

        saveSection('layout', [
            'auth_layout' => 'split',
            'auth_background' => UploadedFile::fake()->image('panel.jpg', 1200, 800),
        ]);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->whereNot('branding.authBackground', null));
    });
});

describe('restoring the defaults', function () {
    test('discards every stored setting', function () {
        saveSection('brand', ['brand_name' => 'Acme Identity']);
        saveSection('layout', ['rows_per_page' => 50]);

        $this->actingAs($this->admin)->delete(route('application-settings.destroy'))
            ->assertRedirect(route('application-settings.edit', ['tab' => 'brand']));

        expect(Setting::query()->count())->toBe(0)
            ->and(storedSettings()->get('brand_name'))->toBe('Laravel SSO');
    });

    test('deletes the imagery it will no longer show', function () {
        Storage::fake('public');

        saveSection('brand', ['logo' => UploadedFile::fake()->image('logo.png')]);
        $path = storedSettings()->get('brand_logo');

        $this->actingAs($this->admin)->delete(route('application-settings.destroy'));

        Storage::disk('public')->assertMissing($path);
    });

    test('is not something an ordinary reader can do', function () {
        saveSection('brand', ['brand_name' => 'Acme Identity']);

        $this->actingAs(User::factory()->create())
            ->delete(route('application-settings.destroy'))
            ->assertForbidden();

        expect(storedSettings()->get('brand_name'))->toBe('Acme Identity');
    });
});

describe('permission isolation', function () {
    test('managing settings needs the settings permission', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::UsersManage->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::UsersManage->value);

        assertPageRefused(
            $this->actingAs($viewer)->get(route('application-settings.edit'))
        );

        $this->actingAs($viewer)
            ->post(route('application-settings.update'), ['section' => 'brand', 'brand_name' => 'Acme'])
            ->assertForbidden();

        expect(Setting::query()->count())->toBe(0);
    });

    test('the tab is offered only to someone who may use it', function () {
        $this->actingAs($this->admin)->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->where('can.manageSettings', true));

        $this->actingAs(User::factory()->create())->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->where('can.manageSettings', false));
    });
});
