<?php

use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;

test('seeding creates exactly one active owner', function () {
    $this->seed();

    $owners = User::query()->where('is_owner', true)->get();

    expect($owners)->toHaveCount(1)
        ->and($owners->first()->status)->toBe(UserStatus::Active);
});

test('seeding again creates nothing twice', function () {
    $this->seed();
    $this->seed();

    expect(User::query()->where('is_owner', true)->count())->toBe(1)
        ->and(Setting::query()->count())->toBe(1)
        ->and(MessageTemplate::query()->count())->toBe(6);
});

test('seeding creates the studio settings with their defaults', function () {
    $this->seed();

    $settings = Setting::current();

    expect($settings->reminders_enabled)->toBeTrue()
        ->and($settings->reminder_threshold_days)->toBe(14)
        ->and($settings->free_cancellation_hours)->toBe(24)
        ->and($settings->retention_months)->toBe(60)
        ->and($settings->ticker_enabled)->toBeTrue();
});

test('seeding creates six message templates and no payment confirmation', function () {
    $this->seed();

    expect(MessageTemplate::query()->orderBy('key')->pluck('key')->all())->toBe([
        'file_ready', 'payment_request', 're_engagement', 'reminder', 'statement_body', 'statement_subject',
    ]);
});
