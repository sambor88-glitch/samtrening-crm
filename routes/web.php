<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Http\Controllers\ClientFileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/pulpit');

// A client opens this without an account: the signature is the permission, and it expires after
// fourteen days. A logged-in trainer gets in the same way the policy lets them see the card.
Route::get('/p/{file}', ClientFileController::class)->name('client-files.show');

// Trainer panel. Screens whose story has not landed yet show a placeholder naming it.
Route::middleware(['auth', 'active'])->group(function () {
    Route::view('/pulpit', 'pages.dashboard')->name('dashboard');
    Route::view('/klienci', 'pages.clients')->name('clients.index');

    // A swapped id must bounce off the policy, not off the screen — docs/START-TUTAJ.md §7.
    Route::get('/klienci/{client}', fn (Client $client) => view('pages.client', ['client' => $client]))
        ->middleware('can:view,client')
        ->name('clients.show');
    Route::view('/sesje', 'pages.sessions')->name('sessions.index');
    Route::view('/platnosci', 'pages.payments')->name('payments.index');
    Route::view('/zarobki', 'pages.earnings')->name('earnings.index');
    Route::view('/wiadomosci', 'pages.messages')->name('messages.index');
    Route::view('/ustawienia', 'pages.settings')->name('settings.index');
});

// Admin panel — the studio owner only; a trainer gets 403 from the `owner` middleware.
Route::middleware(['auth', 'active', 'owner'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'pages.studio-dashboard')->name('dashboard');
    Route::view('/trenerzy', 'pages.trainers')->name('trainers.index');

    // „Podgląd linku": ten sam ekran, który dostał trener, bez ruszania jego tokenu.
    Route::get('/trenerzy/{user}/podglad-linku', fn (User $user) => view('auth.set-password', [
        'mode' => 'invitation',
        'token' => 'podglad',
        'email' => $user->email,
        'account' => $user,
        'preview' => true,
    ]))->name('trainers.preview');
    Route::view('/klienci', 'pages.studio-roster')->name('clients.index');
    Route::view('/zaleglosci', 'pages.studio-outstanding')->name('outstanding.index');
    Route::view('/log', 'pages.activity-log')->name('activity.index');
    Route::view('/wiadomosci', 'pages.messages')->name('messages.index');
    Route::view('/ustawienia', 'pages.settings')->name('settings.index');
});

// Every UI primitive on one page, for side-by-side checks against the prototype. Local only.
if (app()->isLocal()) {
    Route::view('/_styleguide', 'styleguide');
}

require __DIR__.'/auth.php';
