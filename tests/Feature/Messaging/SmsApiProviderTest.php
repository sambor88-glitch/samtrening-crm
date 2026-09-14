<?php

use App\Domain\Messaging\Providers\SmsApiProvider;
use App\Domain\Messaging\Providers\SmsProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * SC-16 — SMSAPI carries the reminders and the payment links. The failures worth covering are the
 * quiet ones: a 200 that is really an error, a missing sender name, a number typed with spaces.
 */
beforeEach(function () {
    config([
        'sms.provider' => 'smsapi',
        'sms.sender' => 'SAMtrening',
        'sms.smsapi.token' => 'token-testowy',
        'sms.smsapi.url' => 'https://api.smsapi.pl/sms.do',
    ]);
});

test('the binding follows the chosen carrier', function () {
    expect(app(SmsProvider::class))->toBeInstanceOf(SmsApiProvider::class);
});

test('the message goes out with the approved sender name and utf-8', function () {
    Http::fake(['api.smsapi.pl/*' => Http::response(['count' => 1, 'list' => [['id' => '1', 'status' => 'QUEUE']]])]);

    app(SmsApiProvider::class)->send('+48 600 300 400', 'Cześć Magdalena, saldo 200 zł.');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.smsapi.pl/sms.do'
            && $request->hasHeader('Authorization', 'Bearer token-testowy')
            && $request['to'] === '48600300400'
            && $request['from'] === 'SAMtrening'
            && $request['encoding'] === 'utf-8'
            && $request['format'] === 'json'
            && $request['message'] === 'Cześć Magdalena, saldo 200 zł.';
    });
});

test('a nine digit number gets the country code, a prefixed one is left alone', function () {
    Http::fake(['api.smsapi.pl/*' => Http::response(['count' => 1])]);

    app(SmsApiProvider::class)->send('600300400', 'x');
    app(SmsApiProvider::class)->send('48 600 300 400', 'x');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request['to'] === '48600300400');
});

test('a number that is not a Polish one is refused before anything is sent', function () {
    Http::fake();

    app(SmsApiProvider::class)->send('12345', 'x');
})->throws(RuntimeException::class, 'nie wygląda na polski numer');

test('an error body served with HTTP 200 is still an error', function () {
    Http::fake(['api.smsapi.pl/*' => Http::response(['error' => 101, 'message' => 'Authorization failed'], 200)]);

    app(SmsApiProvider::class)->send('600300400', 'x');
})->throws(RuntimeException::class, 'SMSAPI odmówił (101)');

test('a body that confirms nothing counts as a failure', function () {
    Http::fake(['api.smsapi.pl/*' => Http::response(['count' => 0, 'list' => []])]);

    app(SmsApiProvider::class)->send('600300400', 'x');
})->throws(RuntimeException::class, 'nie potwierdził przyjęcia');

test('without an approved sender name nothing leaves the studio', function () {
    config(['sms.sender' => null]);
    Http::fake();

    try {
        app(SmsApiProvider::class)->send('600300400', 'x');
    } catch (RuntimeException $refused) {
        expect($refused->getMessage())->toContain('SMS_SENDER');
    }

    Http::assertNothingSent();
});

test('without a token nothing leaves either', function () {
    config(['sms.smsapi.token' => null]);
    Http::fake();

    try {
        app(SmsApiProvider::class)->send('600300400', 'x');
    } catch (RuntimeException $refused) {
        expect($refused->getMessage())->toContain('SMS_API_TOKEN');
    }

    Http::assertNothingSent();
});
