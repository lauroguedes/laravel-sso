<?php

use App\Models\Application;
use App\Models\User;
use App\Oidc\Contracts\OidcAdapter;
use App\Oidc\OidcManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Parser;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Assert that a page visit was refused.
 *
 * A denied navigation no longer answers 403 or 404: it sends the reader back
 * to somewhere they can be, with an explanation. Both halves are asserted
 * here, because the redirect alone would also describe a successful request.
 */
function assertPageRefused(
    TestResponse $response,
    string $expects = 'permission',
    string $type = 'warning',
): void {
    $response->assertRedirect(route('dashboard'));

    $flash = session(SessionKey::FLASH_DATA, []);

    expect($flash['toast']['type'] ?? null)->toBe($type)
        ->and($flash['toast']['message'] ?? '')->toContain($expects);
}

/**
 * Load "config/sso.php" with SSO_DEMO_MODE set, and hand back what it returns.
 *
 * A demo decides two of its switches while that file is read, so setting the
 * key afterwards would prove nothing. The variable is cleared either way, so a
 * failure cannot change the next test's answers.
 *
 * @return array<string, mixed>
 */
function ssoConfigWithDemoMode(bool $enabled): array
{
    Env::getRepository()->set('SSO_DEMO_MODE', $enabled ? 'true' : 'false');

    try {
        return require config_path('sso.php');
    } finally {
        Env::getRepository()->clear('SSO_DEMO_MODE');
    }
}

/**
 * Derive the S256 code challenge for a PKCE verifier.
 */
function pkceChallenge(string $verifier): string
{
    return strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');
}

/**
 * A fresh PKCE verifier and its matching S256 challenge.
 *
 * @return array{0: string, 1: string}
 */
function pkcePair(): array
{
    $verifier = Str::random(64);

    return [$verifier, pkceChallenge($verifier)];
}

/**
 * Start an authorization request against an application.
 *
 * Defaults to the application's first redirect URI and a fresh PKCE pair,
 * which is what almost every case wants; pass overrides for the rest. A null
 * value in the overrides removes that parameter, so a test can ask for a
 * request that omits one.
 *
 * @param  array<string, string|null>  $overrides
 */
function authorizationRequest(
    ?User $user,
    Application $application,
    array $overrides = []
): TestResponse {
    [, $challenge] = pkcePair();

    $parameters = array_filter([
        'client_id' => $application->id,
        'redirect_uri' => $application->redirect_uris[0] ?? null,
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        ...$overrides,
    ], fn (?string $value): bool => $value !== null);

    $test = $user === null ? test() : test()->actingAs($user);

    return $test->get('/oauth/authorize?'.http_build_query($parameters));
}

/**
 * Decode the headers of an ID Token without verifying its signature.
 */
function idTokenHeaders(string $idToken): DataSet
{
    return (new Parser(new JoseEncoder))->parse($idToken)->headers();
}

/**
 * Decode the claims of an ID Token without verifying its signature.
 *
 * Signature and key handling are asserted separately; callers of this only
 * care what the token says.
 */
function idTokenClaims(string $idToken): DataSet
{
    return (new Parser(new JoseEncoder))->parse($idToken)->claims();
}

/*
|--------------------------------------------------------------------------
| OpenID Connect Adapters
|--------------------------------------------------------------------------
|
| Contract tests run every port against each adapter. Behaviour an adapter is
| meant to change stays out of them, and is pinned at the endpoints instead.
|
*/

dataset('oidc adapters', ['native', 'admin9']);

/**
 * The OpenID Connect adapter registered under a name.
 */
function oidcAdapter(string $name): OidcAdapter
{
    return app(OidcManager::class)->driver($name);
}

/**
 * Sign in to an application and redeem the code for its tokens.
 *
 * The application must skip the consent screen and still hold its plain
 * secret, as Application::factory()->trusted()->withSecret() leaves it.
 *
 * @return array<string, mixed>
 */
function issueTokens(User $user, Application $application, string $scope = 'openid email'): array
{
    [$verifier, $challenge] = pkcePair();

    $authorization = authorizationRequest($user, $application, [
        'scope' => $scope,
        'code_challenge' => $challenge,
    ]);

    parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

    return test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $application->id,
        'client_secret' => $application->plainSecret,
        'redirect_uri' => $application->redirect_uris[0],
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->json();
}

/**
 * Run a callback on a server with no key files.
 *
 * Passport is pointed at an empty key directory for the duration, and put back
 * afterwards even when the callback fails, so no later test inherits it.
 *
 * @template TReturn
 *
 * @param  Closure(string): TReturn  $callback  given the empty directory
 * @return TReturn
 */
function withoutKeyFiles(Closure $callback): mixed
{
    $original = Passport::$keyPath;
    $directory = storage_path('framework/testing/keys-'.uniqid());

    File::ensureDirectoryExists($directory);
    Passport::$keyPath = $directory;

    try {
        return $callback($directory);
    } finally {
        Passport::$keyPath = $original;
        File::deleteDirectory($directory);
    }
}
