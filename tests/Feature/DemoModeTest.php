<?php

use App\Providers\AppServiceProvider;
use App\Services\DemoMode;
use Database\Seeders\SsoDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('a demo turns email verification off and fixes it there', function () {
    /*
     * Pinned as well as defaulted off: a demo is administered by whoever
     * walked in, and leaving the switch editable would let the first of them
     * turn the mail back on.
     */
    $demo = ssoConfigWithDemoMode(true);

    expect($demo['defaults']['require_email_verification'])->toBeFalse()
        ->and($demo['pinned'])->toContain('require_email_verification');
});

test('an ordinary installation still requires a verified address', function () {
    $ordinary = ssoConfigWithDemoMode(false);

    expect($ordinary['defaults']['require_email_verification'])->toBeTrue()
        ->and($ordinary['pinned'])->not->toContain('require_email_verification');
});

test('a demo sends no mail at all', function () {
    config()->set('sso.demo.enabled', true);

    (new AppServiceProvider($this->app))->boot();

    expect(config('mail.default'))->toBe('array');
});

test('an ordinary installation keeps its configured transport', function () {
    config()->set('sso.demo.enabled', false);
    config()->set('mail.default', 'smtp');

    (new AppServiceProvider($this->app))->boot();

    expect(config('mail.default'))->toBe('smtp');
});

test('nothing is published while the flag is off', function () {
    /*
     * The file outlives the switch, so reading it is gated on the switch
     * rather than on the file being gone. Turning the demo off is what an
     * operator does when the installation stops being disposable.
     */
    Storage::fake('local');

    config()->set('sso.demo.enabled', true);
    app(DemoMode::class)->rotate('admin@'.SsoDemoSeeder::DOMAIN);

    config()->set('sso.demo.enabled', false);

    expect(app(DemoMode::class)->rotate('admin@'.SsoDemoSeeder::DOMAIN))->toBeNull()
        ->and(app(DemoMode::class)->credentials())->toBeNull();
});

describe('on a public demonstration', function () {
    beforeEach(function () {
        Storage::fake('local');
        config()->set('sso.demo.enabled', true);

        $this->seed(SsoDemoSeeder::class);
    });

    test('the administrator gets a password that is not the shared one', function () {
        $credentials = app(DemoMode::class)->credentials();

        expect($credentials['email'])->toBe('admin@'.SsoDemoSeeder::DOMAIN)
            ->and($credentials['password'])->not->toBe(SsoDemoSeeder::PASSWORD);

        $this->post(route('login'), $credentials);

        $this->assertAuthenticated();
    });

    test('the sign-in page fills those credentials in', function () {
        $credentials = app(DemoMode::class)->credentials();

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->where('demo.email', $credentials['email'])
            ->where('demo.password', $credentials['password'])
        );
    });
});

test('an ordinary installation publishes nothing and fills nothing in', function () {
    Storage::fake('local');

    $this->seed(SsoDemoSeeder::class);

    Storage::disk('local')->assertDirectoryEmpty('/');

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('demo', null));
});
