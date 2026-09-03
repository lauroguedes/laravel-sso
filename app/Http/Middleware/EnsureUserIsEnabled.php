<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user who has been disabled.
 *
 * Rejecting disabled users at the login pipeline is not enough on its own: a
 * user may already hold a session, a remember-me cookie, or have signed in
 * with a passkey, which does not run through that pipeline. This middleware is
 * the single choke point that covers all of them.
 */
class EnsureUserIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user?->isDisabled()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('Your account has been disabled.'),
            ]);
        }

        return $next($request);
    }
}
