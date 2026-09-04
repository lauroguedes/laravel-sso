<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Fortify\Features;

/**
 * Withholds routes from unverified users, but only when this deployment asks
 * users to verify at all.
 *
 * Email verification is a deployment switch. With it turned off Fortify
 * registers no verification routes, so the framework's own middleware would
 * redirect to a route that does not exist. Deciding that here keeps the User
 * model truthful: hasVerifiedEmail() reports whether the address was actually
 * confirmed, which is what the "email_verified" claim and the interface both
 * need to be able to trust.
 */
class EnsureEmailIsVerifiedWhenRequired extends EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  string|null  $redirectToRoute
     * @return Response|RedirectResponse|null
     */
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (! Features::enabled(Features::emailVerification())) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
