<?php

use App\Models\Application;
use App\Models\ApplicationPermission;
use App\Models\ApplicationRole;
use App\Models\ApplicationUser;
use App\Models\User;
use Lcobucci\JWT\Token\DataSet;

/**
 * The claims a token carries must describe the requesting application only.
 *
 * A user holding "Admin" in reporting and "Viewer" in billing must never see
 * reporting's authorization in a token minted for billing.
 */
const CLAIMS_REDIRECT_URI = 'https://claims.example.com/auth/callback';

/**
 * Give a user a role in an application, creating the role and its permissions.
 *
 * @param  array<int, string>  $permissions
 */
function grantRole(User $user, Application $application, string $role, array $permissions = []): ApplicationRole
{
    $applicationRole = ApplicationRole::factory()->create([
        'application_id' => $application->id,
        'name' => $role,
    ]);

    foreach ($permissions as $permission) {
        $applicationRole->permissions()->attach(
            ApplicationPermission::factory()->create([
                'application_id' => $application->id,
                'name' => $permission,
            ])
        );
    }

    ApplicationUser::create([
        'application_id' => $application->id,
        'user_id' => $user->id,
        'application_role_id' => $applicationRole->id,
    ]);

    return $applicationRole;
}

/**
 * Complete the authorization code flow and return the decoded token payload.
 *
 * @return array{id_token_claims: DataSet, access_token: string}
 */
function issueTokensFor($test, User $user, Application $application, string $scope): array
{
    [$verifier, $challenge] = pkcePair();

    $authorization = $test->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $application->id,
        'redirect_uri' => CLAIMS_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => $scope,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

    $tokens = $test->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $application->id,
        'client_secret' => $application->plainSecret,
        'redirect_uri' => CLAIMS_REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->json();

    return [
        'id_token_claims' => idTokenClaims($tokens['id_token']),
        'access_token' => $tokens['access_token'],
    ];
}

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->reporting = Application::factory()
        ->trusted()
        ->withRolesScope()
        ->withSecret('reporting-secret')
        ->create([
            'name' => 'Reporting',
            'redirect_uris' => [CLAIMS_REDIRECT_URI],
        ]);
});

test('the id token carries the role and permissions held in that application', function () {
    grantRole($this->user, $this->reporting, 'Analyst', ['reports.view', 'reports.export']);

    $claims = issueTokensFor($this, $this->user, $this->reporting, 'openid roles')['id_token_claims'];

    expect($claims->get('roles'))->toBe(['Analyst'])
        ->and($claims->get('permissions'))->toEqualCanonicalizing(['reports.view', 'reports.export']);
});

test('the id token omits authorization claims when the roles scope was not granted', function () {
    grantRole($this->user, $this->reporting, 'Analyst', ['reports.view']);

    $claims = issueTokensFor($this, $this->user, $this->reporting, 'openid profile')['id_token_claims'];

    expect($claims->has('roles'))->toBeFalse()
        ->and($claims->has('permissions'))->toBeFalse();
});

test('a user with access but no role reports empty authorization', function () {
    ApplicationUser::create([
        'application_id' => $this->reporting->id,
        'user_id' => $this->user->id,
        'application_role_id' => null,
    ]);

    $claims = issueTokensFor($this, $this->user, $this->reporting, 'openid roles')['id_token_claims'];

    expect($claims->get('roles'))->toBe([])
        ->and($claims->get('permissions'))->toBe([]);
});

test('a user with no access to the application reports empty authorization', function () {
    $claims = issueTokensFor($this, $this->user, $this->reporting, 'openid roles')['id_token_claims'];

    expect($claims->get('roles'))->toBe([])
        ->and($claims->get('permissions'))->toBe([]);
});

test('an application never sees the roles a user holds in another application', function () {
    $billing = Application::factory()
        ->trusted()
        ->withRolesScope()
        ->withSecret('billing-secret')
        ->create([
            'name' => 'Billing',
            'redirect_uris' => [CLAIMS_REDIRECT_URI],
        ]);

    grantRole($this->user, $this->reporting, 'Admin', ['reports.manage', 'users.manage']);
    grantRole($this->user, $billing, 'Viewer', ['invoices.view']);

    $reportingClaims = issueTokensFor($this, $this->user, $this->reporting, 'openid roles')['id_token_claims'];
    $billingClaims = issueTokensFor($this, $this->user, $billing, 'openid roles')['id_token_claims'];

    expect($reportingClaims->get('roles'))->toBe(['Admin'])
        ->and($reportingClaims->get('permissions'))->toEqualCanonicalizing(['reports.manage', 'users.manage']);

    expect($billingClaims->get('roles'))->toBe(['Viewer'])
        ->and($billingClaims->get('permissions'))->toBe(['invoices.view']);
});

test('userinfo scopes authorization to the application that presented the token', function () {
    $billing = Application::factory()
        ->trusted()
        ->withRolesScope()
        ->withSecret('billing-secret')
        ->create([
            'name' => 'Billing',
            'redirect_uris' => [CLAIMS_REDIRECT_URI],
        ]);

    grantRole($this->user, $this->reporting, 'Admin', ['reports.manage']);
    grantRole($this->user, $billing, 'Viewer', ['invoices.view']);

    $billingToken = issueTokensFor($this, $this->user, $billing, 'openid roles')['access_token'];

    $response = $this->getJson('/oauth/userinfo', [
        'Authorization' => 'Bearer '.$billingToken,
    ]);

    $response->assertOk();

    expect($response->json('roles'))->toBe(['Viewer'])
        ->and($response->json('permissions'))->toBe(['invoices.view']);
});

test('userinfo omits authorization claims when the roles scope was not granted', function () {
    grantRole($this->user, $this->reporting, 'Analyst', ['reports.view']);

    $token = issueTokensFor($this, $this->user, $this->reporting, 'openid email')['access_token'];

    $response = $this->getJson('/oauth/userinfo', [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk();

    expect($response->json())->not->toHaveKey('roles')
        ->and($response->json())->not->toHaveKey('permissions');
});

test('an application cannot request the roles scope unless it has been granted it', function () {
    $limited = Application::factory()
        ->trusted()
        ->withSecret('limited-secret')
        ->create([
            'name' => 'Limited',
            'redirect_uris' => [CLAIMS_REDIRECT_URI],
            'scopes' => ['openid'],
        ]);

    grantRole($this->user, $limited, 'Admin', ['everything']);

    $claims = issueTokensFor($this, $this->user, $limited, 'openid roles')['id_token_claims'];

    expect($claims->has('roles'))->toBeFalse();
});

test('removing a role removes it from newly issued tokens', function () {
    $role = grantRole($this->user, $this->reporting, 'Analyst', ['reports.view']);

    ApplicationUser::query()
        ->where('application_id', $this->reporting->id)
        ->where('user_id', $this->user->id)
        ->update(['application_role_id' => null]);

    $role->delete();

    $claims = issueTokensFor($this, $this->user, $this->reporting, 'openid roles')['id_token_claims'];

    expect($claims->get('roles'))->toBe([]);
});
