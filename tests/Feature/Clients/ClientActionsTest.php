<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Clients\Actions\SetClientRate;
use App\Domain\Clients\Actions\UpdateClient;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;

beforeEach(function () {
    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska']);
});

function lastEntry(): ActivityEntry
{
    return ActivityEntry::query()->orderByDesc('id')->firstOrFail();
}

test('a new card lands on the trainer and in the log', function () {
    $client = app(CreateClient::class)->handle($this->trainer, [
        'name' => 'Anna Kowalska',
        'phone' => '+48 600 100 200',
        'rate' => 20000,
        'consent_given' => true,
    ]);

    expect($client->trainer_id)->toBe($this->trainer->id)
        ->and($client->consent_given)->toBeTrue()
        ->and($client->consent_date->toDateString())->toBe(now()->toDateString());

    expect(lastEntry())
        ->action->toBe('Dodał klienta')
        ->context->toBe('Anna Kowalska')
        ->actor_name->toBe('Katarzyna Samborska');
});

test('a card saved without consent says so in the log', function () {
    app(CreateClient::class)->handle($this->trainer, [
        'name' => 'Anna Kowalska',
        'rate' => 20000,
        'consent_given' => false,
    ]);

    expect(lastEntry()->context)->toBe('Anna Kowalska · BRAK ZGODY — uzupełnij przed pierwszą sesją');
});

test('a guardian marks the card as a minor\'s', function () {
    $client = app(CreateClient::class)->handle($this->trainer, [
        'name' => 'Kuba Nowak',
        'rate' => 15000,
        'guardian' => 'Ewa Nowak, +48 600 900 800',
        'consent_given' => true,
    ]);

    expect($client->tags()->pluck('label')->all())->toBe(['Zgoda rodzica'])
        ->and($client->tags()->first()->variant)->toBe('outline');
});

test('an edit names the fields that changed, with both amounts for the rate', function () {
    $client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Anna Kowalska',
        'rate' => 20000,
        'consent_given' => true,
        'consent_date' => now()->subMonth()->toDateString(),
    ]);

    app(UpdateClient::class)->handle($this->trainer, $client, [
        'name' => 'Anna Kowalska',
        'rate' => 22000,
        'contraindications' => 'Kolano — bez wykroków',
        'consent_given' => true,
    ]);

    expect(lastEntry())
        ->action->toBe('Edytował kartę klienta')
        ->context->toBe('Anna Kowalska · przeciwwskazania, stawka 200 zł → 220 zł');

    expect($client->fresh()->consent_date->toDateString())->toBe(now()->subMonth()->toDateString());
});

test('an edit that changes nothing still leaves a line, and says so', function () {
    $client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Anna Kowalska',
        'rate' => 20000,
        'consent_given' => true,
    ]);

    app(UpdateClient::class)->handle($this->trainer, $client, [
        'name' => 'Anna Kowalska',
        'rate' => 20000,
        'consent_given' => true,
    ]);

    expect(lastEntry()->context)->toBe('Anna Kowalska · bez zmian');
});

test('adding a guardian later adds the tag, and only once', function () {
    $client = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Kuba Nowak', 'rate' => 15000]);

    $attributes = ['name' => 'Kuba Nowak', 'rate' => 15000, 'guardian' => 'Ewa Nowak', 'consent_given' => true];

    app(UpdateClient::class)->handle($this->trainer, $client, $attributes);
    app(UpdateClient::class)->handle($this->trainer, $client->fresh(), $attributes);

    expect($client->fresh()->tags()->where('label', 'Zgoda rodzica')->count())->toBe(1);
});

test('withdrawing consent clears the date', function () {
    $client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Anna Kowalska',
        'rate' => 20000,
        'consent_given' => true,
        'consent_date' => now()->toDateString(),
    ]);

    app(UpdateClient::class)->handle($this->trainer, $client, [
        'name' => 'Anna Kowalska',
        'rate' => 20000,
        'consent_given' => false,
    ]);

    expect($client->fresh()->consent_given)->toBeFalse()
        ->and($client->fresh()->consent_date)->toBeNull()
        ->and(lastEntry()->context)->toBe('Anna Kowalska · zgoda RODO');
});

test('a rate set from the card logs both amounts, and nothing when it did not move', function () {
    $client = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Kowalska', 'rate' => 20000]);

    app(SetClientRate::class)->handle($this->trainer, $client, 22000);

    expect($client->fresh()->rate)->toBe(22000)
        ->and(lastEntry())
        ->action->toBe('Zmienił stawkę')
        ->context->toBe('Anna Kowalska · 200 zł → 220 zł');

    $entries = ActivityEntry::query()->count();
    app(SetClientRate::class)->handle($this->trainer, $client->fresh(), 22000);

    expect(ActivityEntry::query()->count())->toBe($entries);
});
