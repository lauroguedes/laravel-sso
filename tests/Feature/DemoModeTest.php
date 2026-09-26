<?php

use Database\Seeders\SsoDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use LauroGuedes\DemoMode\DemoModeServiceProvider;
use LauroGuedes\DemoMode\Facades\Demo;

/*
 * What a public demonstration does is lauroguedes/laravel-demo-mode's job, and it
 * has its own suite. What is left here is the seam: the two things this project
 * decides for itself, and the three places it hands the package's answers to a
 * visitor.
 */

test('a demo turns email verification off and fixes it there', function () {
    /*
     * Pinned as well as defaulted off: a demo is administered by whoever
     * walked in, and leaving the switch editable would let the first of them
     * turn the mail back on. The package cannot know this — it is a setting
     * that only exists in this project.
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
    /*
     * The package's DisableMail restriction, asserted here rather than taken on
     * trust: ".env.example" promises no mail leaves a demo, and that promise is
     * now kept by a config entry that could be edited out without anything else
     * in this project noticing.
     */
    config()->set('demo.enabled', true);
    config()->set('mail.default', 'smtp');

    app()->register(DemoModeServiceProvider::class, force: true);

    expect(config('mail.default'))->toBe('array');
});

test('an ordinary installation keeps its configured transport', function () {
    config()->set('demo.enabled', false);
    config()->set('mail.default', 'smtp');

    app()->register(DemoModeServiceProvider::class, force: true);

    expect(config('mail.default'))->toBe('smtp');
});

describe('on a public demonstration', function () {
    beforeEach(function () {
        Storage::fake('local');
        config()->set('demo.enabled', true);

        /*
         * Rotate before seeding, the order a reset uses: the password is staged
         * first so the seeder hashes the one the sign-in page will show. Seeding
         * first would leave the page showing a password for the previous run.
         */
        Demo::rotate();

        $this->seed(SsoDemoSeeder::class);
    });

    test('the administrator gets a password that is not the shared one', function () {
        $credentials = Demo::credentials();

        expect($credentials['email'])->toBe('admin@'.SsoDemoSeeder::DOMAIN)
            ->and($credentials['password'])->not->toBe(SsoDemoSeeder::PASSWORD);

        $this->post(route('login'), [
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ]);

        $this->assertAuthenticated();
    });

    test('the sign-in page fills those credentials in', function () {
        $credentials = Demo::credentials();

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->where('demo.enabled', true)
            ->where('demo.credentials.email', $credentials['email'])
            ->where('demo.credentials.password', $credentials['password'])
        );
    });
});

test('an ordinary installation publishes nothing and fills nothing in', function () {
    Storage::fake('local');

    $this->seed(SsoDemoSeeder::class);

    Storage::disk('local')->assertDirectoryEmpty('/');

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->where('demo', ['enabled' => false])
    );
});
