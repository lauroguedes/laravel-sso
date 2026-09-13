<?php

use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

/**
 * The Passport and league/oauth2-server behaviour the native OpenID Connect
 * layer is built on.
 *
 * Binding a nonce and auth_time to an ID Token needs somewhere to store them
 * when a code is issued, and somewhere to read them back before the token
 * response is built. These tests pin the order of those calls, so a Passport
 * upgrade that changes it fails here instead of in a relying party.
 */
const SEAMS_REDIRECT_URI = 'https://seams.example.com/auth/callback';

/**
 * Sign in to a trusted application and redeem the code.
 *
 * @return array<string, mixed>
 */
function seamsIssueTokens(User $user, Application $application): array
{
    [$verifier, $challenge] = pkcePair();

    $authorization = authorizationRequest($user, $application, [
        'scope' => 'openid email',
        'code_challenge' => $challenge,
    ]);

    parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

    return test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $application->id,
        'client_secret' => $application->plainSecret,
        'redirect_uri' => SEAMS_REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->json();
}

describe('token grants', function () {
    beforeEach(function () {
        $calls = $this->calls = new ArrayObject;

        $this->app->bind(AuthCodeRepository::class, fn () => new class($calls) extends AuthCodeRepository
        {
            public function __construct(private ArrayObject $calls) {}

            public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
            {
                $this->calls[] = __FUNCTION__;

                parent::persistNewAuthCode($authCodeEntity);
            }

            public function isAuthCodeRevoked(string $codeId): bool
            {
                $this->calls[] = __FUNCTION__;

                return parent::isAuthCodeRevoked($codeId);
            }

            public function revokeAuthCode(string $codeId): void
            {
                $this->calls[] = __FUNCTION__;

                parent::revokeAuthCode($codeId);
            }
        });

        $this->app->bind(AccessTokenRepository::class, fn ($app) => new class($app->make(Dispatcher::class), $calls) extends AccessTokenRepository
        {
            public function __construct(Dispatcher $events, private ArrayObject $calls)
            {
                parent::__construct($events);
            }

            public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
            {
                $this->calls[] = __FUNCTION__;

                parent::persistNewAccessToken($accessTokenEntity);
            }

            public function revokeAccessToken(string $tokenId): void
            {
                $this->calls[] = __FUNCTION__;

                parent::revokeAccessToken($tokenId);
            }
        });

        /*
         * getExtraParams() runs while the token response is built, which is
         * where an ID Token needs the authorization context.
         */
        Passport::useAuthorizationServerResponseType(new class($calls) extends BearerTokenResponse
        {
            public function __construct(private ArrayObject $calls) {}

            protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
            {
                $this->calls[] = 'buildTokenResponse';

                return parent::getExtraParams($accessToken);
            }
        });

        $this->user = User::factory()->create();

        $this->application = Application::factory()->trusted()->withSecret('seams-secret')->create([
            'redirect_uris' => [SEAMS_REDIRECT_URI],
        ]);
    });

    afterEach(fn () => Passport::useAuthorizationServerResponseType(null));

    test('redeeming a code checks it, stores the access token and revokes the code before the response is built', function () {
        seamsIssueTokens($this->user, $this->application);

        expect($this->calls->getArrayCopy())->toBe([
            'persistNewAuthCode',
            'isAuthCodeRevoked',
            'persistNewAccessToken',
            'revokeAuthCode',
            'buildTokenResponse',
        ]);
    });

    test('refreshing revokes the old access token before storing the new one and building the response', function () {
        $refreshToken = seamsIssueTokens($this->user, $this->application)['refresh_token'];
        $this->calls->exchangeArray([]);

        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->application->id,
            'client_secret' => $this->application->plainSecret,
            'refresh_token' => $refreshToken,
        ])->assertOk();

        expect($this->calls->getArrayCopy())->toBe([
            'revokeAccessToken',
            'persistNewAccessToken',
            'buildTokenResponse',
        ]);
    });

    test('a code is stored only once the user approves the consent screen', function () {
        $application = Application::factory()->create(['redirect_uris' => [SEAMS_REDIRECT_URI]]);

        $consent = authorizationRequest($this->user, $application, ['scope' => 'openid']);

        expect($this->calls->getArrayCopy())->toBe([]);

        $this->actingAs($this->user)->post('/oauth/authorize', [
            'auth_token' => $consent->viewData('page')['props']['authToken'],
        ])->assertRedirectContains(SEAMS_REDIRECT_URI);

        expect($this->calls->getArrayCopy())->toBe(['persistNewAuthCode']);
    });

    test('a trusted application stores the code during the authorization request itself', function () {
        authorizationRequest($this->user, $this->application, ['scope' => 'openid'])
            ->assertRedirectContains(SEAMS_REDIRECT_URI);

        expect($this->calls->getArrayCopy())->toBe(['persistNewAuthCode']);
    });
});

/*
 * auth_time must record when the user actually authenticated. Every sign-in
 * path (password, two factor, registration and passkey) ends in the guard's
 * login(), which raises Login. So does restoring a session from the remember
 * me cookie, which is not a new authentication, and viaRemember() is how the
 * two are told apart.
 */
describe('sign-in', function () {
    beforeEach(function () {
        $viaRemember = $this->viaRemember = new ArrayObject;

        Event::listen(Login::class, fn () => $viaRemember[] = Auth::guard()->viaRemember());

        $this->user = User::factory()->create();
    });

    test('signing in with a password raises the login event as a fresh authentication', function () {
        $this->post(route('login.store'), [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        expect($this->viaRemember->getArrayCopy())->toBe([false]);
    });

    test('restoring a session from the remember me cookie raises the login event as well', function () {
        $recaller = Auth::guard()->getRecallerName();

        $cookie = $this->post(route('login.store'), [
            'email' => $this->user->email,
            'password' => 'password',
            'remember' => 'on',
        ])->getCookie($recaller, decrypt: false);

        Auth::forgetGuards();
        $this->flushSession();
        $this->viaRemember->exchangeArray([]);

        $this->withUnencryptedCookie($recaller, $cookie->getValue())
            ->get(route('dashboard'))
            ->assertOk();

        expect($this->viaRemember->getArrayCopy())->toBe([true]);
    });
});
