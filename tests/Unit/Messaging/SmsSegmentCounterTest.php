<?php

use App\Domain\Messaging\SmsSegmentCounter;

beforeEach(function () {
    $this->counter = new SmsSegmentCounter;
});

test('plain text fits 160 characters in one message and needs two at 161', function () {
    expect($this->counter->count(str_repeat('a', 160)))
        ->segments->toBe(1)
        ->characters->toBe(160)
        ->encoding->toBe('GSM-7');

    expect($this->counter->count(str_repeat('a', 161))->segments)->toBe(2);
});

test('one Polish letter cuts the message down to 70 characters', function () {
    $seventy = str_repeat('a', 69).'ą';

    expect($this->counter->count($seventy))
        ->segments->toBe(1)
        ->characters->toBe(70)
        ->encoding->toBe('UCS-2');

    expect($this->counter->count($seventy.'a')->segments)->toBe(2);
});

test('long texts are cut into concatenated parts, 67 or 153 characters each', function () {
    expect($this->counter->count(str_repeat('a', 306))->segments)->toBe(2)
        ->and($this->counter->count(str_repeat('a', 307))->segments)->toBe(3)
        ->and($this->counter->count(str_repeat('ą', 134))->segments)->toBe(2)
        ->and($this->counter->count(str_repeat('ą', 135))->segments)->toBe(3);
});

test('the label counts the Polish way', function () {
    expect($this->counter->count(str_repeat('a', 1))->label())->toBe('1 znak · 1 wiadomość · GSM-7')
        ->and($this->counter->count(str_repeat('ą', 200))->label())->toBe('200 znaków · 3 wiadomości · UCS-2');
});
