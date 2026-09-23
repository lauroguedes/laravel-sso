<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use LauroGuedes\DemoMode\Facades\Demo;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure how credentials are validated.
     *
     * Both AttemptToAuthenticate and RedirectIfTwoFactorAuthenticatable route
     * their credential check through this callback, so a disabled user is
     * turned away before reaching a two factor challenge. Sessions obtained by
     * other means are ended by the EnsureUserIsEnabled middleware.
     *
     * The failure message does not distinguish a disabled account from a wrong
     * password, so it cannot be used to enumerate accounts.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?Authenticatable {
            $provider = Auth::guard(config('fortify.guard'))->getProvider();

            $credentials = $request->only(Fortify::username(), 'password');

            $user = $provider->retrieveByCredentials($credentials);

            if (! $user || ! $provider->validateCredentials($user, $credentials)) {
                return null;
            }

            return $user instanceof User && $user->isDisabled() ? null : $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        /*
         * Registration is a deployment switch on this server, so its routes
         * are absent when it is disabled and Wayfinder cannot generate a
         * helper for them. Pages that merely link to registration receive the
         * URL from here instead, and render nothing when it is null.
         */
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'registerUrl' => Features::enabled(Features::registration()) ? route('register') : null,
            'status' => $request->session()->get('status'),
            /*
             * ['enabled' => false] on every installation that is not a public
             * demonstration. A demo has to let a stranger in, so it fills its
             * own credentials into the form.
             *
             * Passed to this page only, rather than shared from
             * HandleInertiaRequests: the payload carries the administrator's
             * password, and an Inertia payload is in the page source of every
             * response it decorates. The sign-in page is the one place that is
             * the point.
             */
            'demo' => Demo::toArray(),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'submitUrl' => route('register.store'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
