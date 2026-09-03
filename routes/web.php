<?php

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
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
