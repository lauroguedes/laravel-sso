<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationGrantController;
use App\Http\Controllers\ApplicationPermissionController;
use App\Http\Controllers\ApplicationRoleController;
use App\Http\Controllers\ApplicationSecretController;
use App\Http\Controllers\ApplicationStatusController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration Routes
|--------------------------------------------------------------------------
|
| The whole of this application is an administration console for the Identity
| Provider, so these are top level resources rather than an "admin" area.
| Access is decided by policies; the middleware here only establishes that
| there is a verified, enabled user behind the request.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('users/{user}/status', [UserStatusController::class, 'update'])->name('users.status.update');

    /*
     * Applications are a plain resource. Users are not: they are edited at
     * "users/{user}" with no separate show screen, so those stay explicit.
     */
    Route::resource('applications', ApplicationController::class)->except('destroy');

    Route::put('applications/{application}/status', [ApplicationStatusController::class, 'update'])
        ->name('applications.status.update');
    Route::put('applications/{application}/secret', [ApplicationSecretController::class, 'update'])
        ->name('applications.secret.update');

    /*
     * Roles, permissions and access grants belong to one application. Scoped
     * bindings make Laravel resolve each child through its parent, so a URL
     * naming another application's role resolves to nothing rather than
     * editing it.
     */
    Route::resource('applications.roles', ApplicationRoleController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped();

    Route::resource('applications.permissions', ApplicationPermissionController::class)
        ->only(['store', 'destroy'])
        ->scoped();

    Route::resource('applications.grants', ApplicationGrantController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped();
});
