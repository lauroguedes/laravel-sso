<?php

use App\Http\Controllers\Settings\ApplicationSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    /*
     * There is deliberately no route for deleting your own account. This
     * server disables accounts rather than deleting them, so that the audit
     * trail keeps naming a real person; a self-service delete would leave
     * their entries with no causer and silently drop their access grants.
     * Administrators disable an account from the user's page.
     */
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    /*
     * How the whole installation presents itself, as opposed to the settings
     * above, which each reader owns. The controller authorizes; the route is
     * here so the tab shares the settings layout.
     */
    Route::get('settings/application', [ApplicationSettingsController::class, 'edit'])
        ->name('application-settings.edit');

    Route::post('settings/application', [ApplicationSettingsController::class, 'update'])
        ->name('application-settings.update');

    Route::delete('settings/application', [ApplicationSettingsController::class, 'destroy'])
        ->name('application-settings.destroy');

    /*
     * No appearance route: light, dark and system live in the application
     * header, where they are reachable from every page rather than from one
     * settings tab.
     */
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
