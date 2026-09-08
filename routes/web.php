<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * The root of an Identity Provider is the way in, not a page about itself.
 * Somebody arriving here either has a session, in which case they want the
 * dashboard, or they do not, in which case the only thing on offer is signing
 * in. There is nothing else this server could usefully say to a stranger.
 *
 * Still named "home": it is where signing out returns to, and where a wrong
 * address sends an anonymous reader.
 */
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

/*
 * A URL that matches nothing still has to explain itself.
 *
 * The exception handler turns a 404 into a redirect carrying a toast, but an
 * unmatched URI never enters the "web" group, so there is no session for that
 * message to survive in and it arrives silently. Matching here puts the
 * request inside the group before the 404 is raised.
 */
Route::fallback(function (Request $request) {
    /*
     * Except under the protocol and API prefixes, where a relying party is
     * written against the status code and a redirect to a sign-in page would
     * read as a working endpoint.
     */
    if ($request->expectsJson() || $request->is('api/*', 'oauth/*', '.well-known/*')) {
        abort(404);
    }

    Inertia::flash('toast', [
        /* A wrong address is about the request, not a fault of this server. */
        'type' => 'warning',
        'message' => __('That page does not exist.'),
    ]);

    /*
     * Straight to where the reader ends up, not via "home". Flash data
     * survives exactly one request, and "home" is itself a redirect — the
     * explanation would be spent on the hop and the page would arrive silent.
     */
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});
