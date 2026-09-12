<?php

use App\Domain\Clients\Enums\RosterFilter;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Queries\ClientRoster;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->roster = app(ClientRoster::class);
    $this->trainer = User::factory()->create();
});

function client(User $trainer, string $name, int $rate = 20000): Client
{
    return Client::factory()->for($trainer, 'trainer')->create(['name' => $name, 'rate' => $rate]);
}

test('a row carries the balance and the date of the last session', function () {
    $magda = client($this->trainer, 'Magdalena Wróbel');
    TrainingSession::factory()->for($magda)->on('2026-09-02')->paid()->create();
    TrainingSession::factory()->for($magda)->on('2026-09-09')->create(['price' => 20000]);

    $row = $this->roster->forTrainer($this->trainer)->rows->sole();

    expect($row->balance)->toBe(20000)
        ->and($row->owes())->toBeTrue()
        ->and($row->lastSessionOn->toDateString())->toBe('2026-09-09');
});

test('a client nobody has trained yet has no date and owes nothing', function () {
    client($this->trainer, 'Nowy Klient');

    $row = $this->roster->forTrainer($this->trainer)->rows->sole();

    expect($row->lastSessionOn)->toBeNull()
        ->and($row->balance)->toBe(0)
        ->and($row->owes())->toBeFalse();
});

test('the roster holds one trainer\'s clients and nobody else\'s', function () {
    client($this->trainer, 'Moja Klientka');
    client(User::factory()->create(), 'Cudzy Klient');

    $names = $this->roster->forTrainer($this->trainer)->rows->map(fn ($row) => $row->client->name);

    expect($names->all())->toBe(['Moja Klientka']);
});

test('search looks at the name, the phone and the tags, and ignores capital letters', function () {
    $magda = client($this->trainer, 'Magdalena Wróbel');
    $magda->update(['phone' => '+48 600 100 200']);
    $magda->tags()->create(['label' => 'Zdrowa ciąża', 'variant' => 'accent']);
    client($this->trainer, 'Aleksander Górski');

    $found = fn (string $search) => $this->roster->forTrainer($this->trainer, RosterFilter::Active, $search)
        ->rows->map(fn ($row) => $row->client->name)->all();

    expect($found('wróbel'))->toBe(['Magdalena Wróbel'])
        ->and($found('600 100'))->toBe(['Magdalena Wróbel'])
        ->and($found('ciąża'))->toBe(['Magdalena Wróbel'])
        ->and($found('górski'))->toBe(['Aleksander Górski'])
        ->and($found('nikt taki'))->toBe([]);
});

test('a percent sign in the search is a character to look for, not a wildcard', function () {
    client($this->trainer, 'Anna Kowalska');
    client($this->trainer, 'Bartek Nowak');

    $rows = $this->roster->forTrainer($this->trainer, RosterFilter::Active, 'Ann%a')->rows;

    expect($rows->map(fn ($row) => $row->client->name)->all())->toBe(['Anna Kowalska']);
});

test('the balance filter and the search narrow the list together', function () {
    $magda = client($this->trainer, 'Magdalena Wróbel');
    TrainingSession::factory()->for($magda)->create(['price' => 20000]);

    $olek = client($this->trainer, 'Aleksander Górski');
    TrainingSession::factory()->for($olek)->paid()->create();

    $owing = $this->roster->forTrainer($this->trainer, RosterFilter::Owing);
    $owingAndSearched = $this->roster->forTrainer($this->trainer, RosterFilter::Owing, 'Górski');

    expect($owing->rows->map(fn ($row) => $row->client->name)->all())->toBe(['Magdalena Wróbel'])
        ->and($owingAndSearched->rows)->toBeEmpty();
});

test('the online filter keeps the clients tagged E-trening', function () {
    $online = client($this->trainer, 'Aleksander Górski');
    $online->tags()->create(['label' => 'E-trening online', 'variant' => 'accent-2']);
    client($this->trainer, 'Magdalena Wróbel');

    $rows = $this->roster->forTrainer($this->trainer, RosterFilter::Online)->rows;

    expect($rows->map(fn ($row) => $row->client->name)->all())->toBe(['Aleksander Górski']);
});

test('the archive filter swaps the roster for the archived clients', function () {
    client($this->trainer, 'Aktywna Klientka');
    Client::factory()->for($this->trainer, 'trainer')->archived()->create(['name' => 'Dawny Klient']);

    $archive = $this->roster->forTrainer($this->trainer, RosterFilter::Archived);
    $active = $this->roster->forTrainer($this->trainer);

    expect($archive->rows->map(fn ($row) => $row->client->name)->all())->toBe(['Dawny Klient'])
        ->and($archive->total)->toBe(1)
        ->and($active->rows->map(fn ($row) => $row->client->name)->all())->toBe(['Aktywna Klientka']);
});

test('the counter counts what the filter could show, not what the search found', function () {
    client($this->trainer, 'Anna Kowalska');
    client($this->trainer, 'Bartek Nowak');
    client($this->trainer, 'Celina Zielińska');

    $roster = $this->roster->forTrainer($this->trainer, RosterFilter::Active, 'Anna');

    expect($roster->rows)->toHaveCount(1)
        ->and($roster->total)->toBe(3);
});

test('three hundred clients cost the same queries as five', function () {
    $fill = function (int $count) {
        Client::factory()->count($count)->for($this->trainer, 'trainer')->create()
            ->take(20)
            ->each(fn (Client $client) => TrainingSession::factory()->for($client)->create());
    };

    $countQueries = function () {
        DB::flushQueryLog();
        $this->roster->forTrainer($this->trainer);

        return count(DB::getQueryLog());
    };

    DB::enableQueryLog();

    $fill(5);
    $small = $countQueries();

    $fill(295);
    $large = $countQueries();

    DB::disableQueryLog();

    expect($large)->toBe($small)
        ->and($large)->toBeLessThanOrEqual(5);
});
