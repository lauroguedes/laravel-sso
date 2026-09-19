<?php

use App\Http\Middleware\EnsureEmailIsVerifiedWhenRequired;
use App\Http\Middleware\EnsureUserIsEnabled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::group([], base_path('routes/oidc.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        /*
         * Verification is a deployment switch, so the framework's middleware
         * is replaced by one that stands aside when this server never asks
         * users to confirm their address.
         */
        $middleware->alias(['verified' => EnsureEmailIsVerifiedWhenRequired::class]);

        $middleware->web(append: [
            EnsureUserIsEnabled::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * A page the reader may not open, or that does not exist, sends them
         * somewhere they can be with an explanation, rather than replacing the
         * interface with an error page. Losing the whole application to a
         * mistyped URL is a worse answer than a sentence.
         *
         * Only for the interface: API and JSON callers, and the OAuth protocol
         * endpoints, still receive the status code they are written against.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if ($status < 400) {
                return $response;
            }

            /*
             * Only a page visit. A failed form submission keeps its status:
             * the interface already hides controls the reader may not use, so
             * one arriving is a client acting out of turn, and answering it
             * with a redirect would tell it the request had been handled.
             */
            if (! $request->isMethod('GET')) {
                return $response;
            }

            if ($request->expectsJson() || $request->is('api/*', 'oauth/*', '.well-known/*')) {
                return $response;
            }

            /*
             * A server fault with debugging on keeps the exception page: that
             * page is the reason debugging is on, and swapping it for a
             * sentence would hide the stack trace a developer is waiting for.
             */
            if ($status >= 500 && config('app.debug') === true) {
                return $response;
            }

            $destination = $request->user() === null ? 'login' : 'dashboard';

            /*
             * A fault on the page we would send them to would otherwise
             * redirect to itself, fault again, and loop.
             */
            if ($request->routeIs($destination)) {
                return $response;
            }

            Inertia::flash('toast', [
                /*
                 * A 4xx is something about this request — the wrong address,
                 * a page that is not theirs — and is worth a warning. A 5xx is
                 * this server failing, which is not their fault and is worth
                 * the stronger colour.
                 */
                'type' => $status >= 500 ? 'error' : 'warning',
                'message' => match (true) {
                    $status === 403 => __('You do not have permission to open that page.'),
                    $status === 404 => __('That page does not exist.'),
                    $status === 419 => __('Your session expired. Please try again.'),
                    $status === 429 => __('Too many requests. Please wait a moment and try again.'),
                    $status >= 500 => __('Something went wrong on this server. Please try again.'),
                    default => __('That request could not be completed.'),
                },
            ]);

            return redirect()->route($destination);
        });
    })->create();
