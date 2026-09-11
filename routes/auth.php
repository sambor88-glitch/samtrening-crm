<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

// Ekrany dostępu 1–3. Rejestracji nie ma — konta zakłada właściciel studia.
Route::middleware('guest')->group(function () {
    Route::get('logowanie', [AuthenticatedSessionController::class, 'create'])->name('login');

    Route::post('logowanie', [AuthenticatedSessionController::class, 'store']);

    Route::get('nie-pamietam-hasla', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Sześć próśb na minutę: stąd nikt nie zasypie skrzynki trenera.
    Route::post('nie-pamietam-hasla', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('nowe-haslo/{token}', [NewPasswordController::class, 'create'])->name('password.reset');

    Route::post('nowe-haslo', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::put('haslo', [PasswordController::class, 'update'])->name('password.update');

    Route::post('wyloguj', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
