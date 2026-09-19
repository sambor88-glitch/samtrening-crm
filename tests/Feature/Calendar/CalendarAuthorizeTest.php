<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('calendar.client_id', 'id-oauth');
    config()->set('calendar.client_secret', 'sekret-oauth');
    config()->set('calendar.account', 'maciej.samborski@samtrening.com');
});

test('the first pass prints a consent url asking only to read', function () {
    $this->artisan('calendar:authorize')
        ->expectsOutputToContain('calendar.readonly')
        ->expectsOutputToContain('maciej.samborski@samtrening.com')
        ->assertSuccessful();
});

test('the consent url asks for a refresh token', function () {
    // Without access_type=offline and prompt=consent Google hands back nothing
    // reusable — and this account already consented once, for Gmail.
    // Both sit in the same URL line, so they are asserted as one string.
    $this->artisan('calendar:authorize')
        ->expectsOutputToContain('access_type=offline&prompt=consent')
        ->assertSuccessful();
});

test('the first pass says the post keeps working', function () {
    $this->artisan('calendar:authorize')
        ->expectsOutputToContain('Wysyłka poczty działa dalej')
        ->assertSuccessful();
});

test('the second pass trades the code for a refresh token', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['refresh_token' => 'refresh-kalendarza'])]);

    $this->artisan('calendar:authorize', ['--code' => 'kod-z-paska'])
        ->expectsOutputToContain('GOOGLE_CALENDAR_REFRESH_TOKEN=refresh-kalendarza')
        ->assertSuccessful();

    Http::assertSent(fn (Request $r) => $r['code'] === 'kod-z-paska' && $r['grant_type'] === 'authorization_code');
});

test('a percent-encoded code copied from the address bar still works', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['refresh_token' => 'refresh'])]);

    $this->artisan('calendar:authorize', ['--code' => '4%2F0AX4Xf'])->assertSuccessful();

    Http::assertSent(fn (Request $r) => $r['code'] === '4/0AX4Xf');
});

test('the second pass tells the user not to touch the mail token', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['refresh_token' => 'refresh'])]);

    $this->artisan('calendar:authorize', ['--code' => 'kod'])
        ->expectsOutputToContain('GMAIL_REFRESH_TOKEN zostaw bez zmian')
        ->assertSuccessful();
});

test('a consent that returns no refresh token explains how to fix it', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'tylko-dostep'])]);

    $this->artisan('calendar:authorize', ['--code' => 'kod'])
        ->expectsOutputToContain('myaccount.google.com/permissions')
        ->assertFailed();
});

test('a rejected code fails loudly', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->artisan('calendar:authorize', ['--code' => 'przeterminowany'])
        ->expectsOutputToContain('invalid_grant')
        ->assertFailed();
});

test('without OAuth credentials the command says where to get them', function () {
    config()->set('calendar.client_id', null);

    $this->artisan('calendar:authorize')
        ->expectsOutputToContain('GMAIL_CLIENT_ID')
        ->assertFailed();
});
