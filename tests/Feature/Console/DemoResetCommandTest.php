<?php

use App\Models\User;

test('it refuses unless the installation says it is a demo', function () {
    /*
     * The command drops every table. Refusing by default means a deployment
     * has to say out loud that this installation holds nothing worth keeping.
     */
    config()->set('sso.demo.enabled', false);

    $user = User::factory()->create();

    $this->artisan('sso:demo-reset', ['--force' => true])
        ->expectsOutputToContain('not a demo')
        ->assertExitCode(1);

    expect(User::whereKey($user->id)->exists())->toBeTrue();
});

test('it refuses in production, where the demo passwords are published', function () {
    config()->set('sso.demo.enabled', true);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('sso:demo-reset', ['--force' => true])
        ->expectsOutputToContain('production')
        ->assertExitCode(1);
});

/*
 * What the command does once past its guards is "migrate:fresh" and a seeder,
 * both of which the framework and SsoDemoSeederTest already cover — and
 * neither can run inside the transaction a test is wrapped in, since sqlite
 * refuses to vacuum from within one. The guards are what belongs to this
 * command, so the guards are what is tested.
 */
