<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

/*
 * Registration is a deployment switch, so its route only exists when the
 * feature is enabled. The landing page receives the URL rather than resolving
 * it client side, and hides the call to action when it is null.
 */
Route::get('/', fn () => Inertia::render('Welcome', [
    'registerUrl' => Features::enabled(Features::registration()) ? route('register') : null,
]))->name('home');

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

    return redirect()->route(auth()->check() ? 'dashboard' : 'home');
});
