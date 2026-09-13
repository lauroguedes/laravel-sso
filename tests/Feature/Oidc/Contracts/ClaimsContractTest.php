<?php

use App\Models\Application;
use App\Models\ApplicationRole;
use App\Models\User;

/**
 * How every adapter turns a user and the granted scopes into claims.
 */
test('claims carry the subject and only what the scopes grant', function (string $adapter) {
    $user = User::factory()->create();

    expect(oidcAdapter($adapter)->claims()->claimsFor($user, ['openid', 'email'], null))
        ->toMatchArray([
            'sub' => (string) $user->id,
            'email' => $user->email,
        ])
        ->not->toHaveKey('name');
})->with('oidc adapters');

test('authorization claims describe only the application that asks', function (string $adapter) {
    $user = User::factory()->create();
    $application = Application::factory()->create();

    $application->grantAccessTo($user, ApplicationRole::factory()->create([
        'application_id' => $application->id,
        'name' => 'Editor',
    ]));

    $claims = oidcAdapter($adapter)->claims();

    expect($claims->claimsFor($user, ['openid', 'roles'], $application->id))
        ->toMatchArray(['roles' => ['Editor'], 'permissions' => []])
        ->and($claims->claimsFor($user, ['openid', 'roles'], Application::factory()->create()->id))
        ->toMatchArray(['roles' => [], 'permissions' => []])
        ->and($claims->claimsFor($user, ['openid', 'roles'], null))
        ->not->toHaveKey('roles');
})->with('oidc adapters');
