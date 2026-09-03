<?php

use App\Http\Controllers\ApplicationController;
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

    Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('applications/create', [ApplicationController::class, 'create'])->name('applications.create');
    Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::get('applications/{application}/edit', [ApplicationController::class, 'edit'])->name('applications.edit');
    Route::put('applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
    Route::put('applications/{application}/status', [ApplicationStatusController::class, 'update'])
        ->name('applications.status.update');
    Route::put('applications/{application}/secret', [ApplicationSecretController::class, 'update'])
        ->name('applications.secret.update');
});
