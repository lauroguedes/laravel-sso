<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Concerns\IdentifiesAuthorizationRequests;
use App\Exceptions\ApplicationAccessDenied;
use App\Models\Application;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns away users who have not been granted access to the application.
 *
 * Only applies to applications an administrator has marked as restricted;
 * every other application admits any authenticated user.
 *
 * Checked when the authorization starts, which is the only point that names
 * the client. That is sufficient: the approval which follows needs an auth
 * token that only a request passing this check puts in the session.
 *
 * How the refusal is presented belongs to ApplicationAccessDenied.
 */
class EnsureApplicationAdmitsUser
{
    use IdentifiesAuthorizationRequests;

    public function __construct(private readonly ClientRepository $clients) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * Checked before touching the guard, because this group also covers
         * the unauthenticated discovery endpoints and resolving a user there
         * would start a session for nothing.
         */
        if (! $this->isAuthorizationStart($request)) {
            return $next($request);
        }

        $user = Auth::user();
        $application = $this->applicationFor($request);

        if ($user === null || $application === null || $application->admits($user)) {
            return $next($request);
        }

        throw new ApplicationAccessDenied($application);
    }

    /**
     * The application the request names, when it names a usable one.
     *
     * An unknown or revoked client is left alone: the OAuth2 server rejects it
     * with a protocol error, which is a better answer than this one.
     */
    private function applicationFor(Request $request): ?Application
    {
        $clientId = $request->string('client_id')->toString();

        if ($clientId === '') {
            return null;
        }

        $client = $this->clients->findActive($clientId);

        return $client instanceof Application ? $client : null;
    }
}
