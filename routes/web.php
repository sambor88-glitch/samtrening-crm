<?php

use App\Domain\Clients\Models\Client;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/pulpit');

// Trainer panel. Screens whose story has not landed yet show a placeholder naming it.
Route::middleware('auth')->group(function () {
    Route::view('/pulpit', 'pages.placeholder', ['title' => 'Pulpit', 'story' => 'SC-41'])->name('dashboard');
    Route::view('/klienci', 'pages.clients')->name('clients.index');

    // A swapped id must bounce off the policy, not off the screen — docs/START-TUTAJ.md §7.
    Route::get('/klienci/{client}', fn (Client $client) => view('pages.client', ['client' => $client]))
        ->middleware('can:view,client')
        ->name('clients.show');
    Route::view('/sesje', 'pages.sessions')->name('sessions.index');
    Route::view('/platnosci', 'pages.payments')->name('payments.index');
    Route::view('/zarobki', 'pages.earnings')->name('earnings.index');
    Route::view('/wiadomosci', 'pages.messages')->name('messages.index');
    Route::view('/ustawienia', 'pages.placeholder', ['title' => 'Ustawienia', 'story' => 'SC-44'])->name('settings.index');
});

// Admin panel — the studio owner only; a trainer gets 403 from the `owner` middleware.
Route::middleware(['auth', 'owner'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'pages.placeholder', ['title' => 'Pulpit studia', 'story' => 'SC-35'])->name('dashboard');
    Route::view('/trenerzy', 'pages.placeholder', ['title' => 'Trenerzy', 'story' => 'SC-36'])->name('trainers.index');
    Route::view('/klienci', 'pages.placeholder', ['title' => 'Kartoteka studia', 'story' => 'SC-38'])->name('clients.index');
    Route::view('/zaleglosci', 'pages.placeholder', ['title' => 'Zaległości studia', 'story' => 'SC-39'])->name('outstanding.index');
    Route::view('/log', 'pages.placeholder', ['title' => 'Log zmian', 'story' => 'SC-40'])->name('activity.index');
    Route::view('/wiadomosci', 'pages.messages')->name('messages.index');
    Route::view('/ustawienia', 'pages.placeholder', ['title' => 'Ustawienia', 'story' => 'SC-44'])->name('settings.index');
});

// Every UI primitive on one page, for side-by-side checks against the prototype. Local only.
if (app()->isLocal()) {
    Route::view('/_styleguide', 'styleguide');
}

require __DIR__.'/auth.php';
