<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\DemoMode;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

/**
 * Keeps the documentation for signed-in users, except on a public demo.
 *
 * A demo exists to be explored, so there the docs are open to anyone. Anywhere
 * else this is Laravel's own authentication middleware.
 */
class EnsureDocumentationIsReadable extends Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  string  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        if (app(DemoMode::class)->enabled()) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }
}
