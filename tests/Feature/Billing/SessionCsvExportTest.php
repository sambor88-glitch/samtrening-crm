<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Export\SessionCsvExport;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\Earnings;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * The file as data: BOM off, lines split, fields unquoted — so the tests talk about columns
 * rather than about where PHP decided to put quotation marks.
 *
 * @return array<int, array<int, string>>
 */
function csvRows(string $contents): array
{
    return collect(explode("\r\n", trim(ltrim($contents, "\u{FEFF}"))))
        ->map(fn (string $line) => str_getcsv($line, ';', '"', ''))
        ->all();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    $this->trainer = User::factory()->create(['name' => 'Maciej Samborski']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Magdalena Wróbel']);
    $this->export = app(SessionCsvExport::class);
});

test('the file starts with a BOM and the Polish headers, split by semicolons', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create();

    $file = $this->export->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'));

    expect($file->contents)->toStartWith("\u{FEFF}")
        ->and(csvRows($file->contents)[0])->toBe(['Data', 'Klient', 'Usługa', 'Rodzaj', 'Kwota PLN', 'Status płatności'])
        ->and($file->name)->toBe('samtrening-2026-09-maciej.csv');
});

test('a row carries the facts and the diacritics, and never the session note', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create([
        'service' => 'Zdrowa ciąża 1:1',
        'price' => 20000,
        'notes' => 'Kolano — bez wykroków',
    ]);

    $file = $this->export->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'));

    expect(csvRows($file->contents)[1])
        ->toBe(['2026-09-09', 'Magdalena Wróbel', 'Zdrowa ciąża 1:1', 'Odbyta', '200', 'Na saldzie']);

    expect($file->contents)
        ->not->toContain('Kolano')
        ->not->toContain('zł');
});

test('an amount with grosze keeps them, with a comma the way Excel expects', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 19950]);

    $row = csvRows($this->export->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'))->contents)[1];

    expect($row[4])->toBe('199,50');
});

test('cancellations and payments are named in Polish, not in enum values', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->cancelled()->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-09-03')->noShow()->paid()->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-09-04')->waived()->create();

    $contents = $this->export->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'))->contents;
    $rows = csvRows($contents);

    expect(array_slice($rows[1], 3))->toBe(['Odwołana', '200', 'Na saldzie'])
        ->and(array_slice($rows[2], 3))->toBe(['Nieobecność', '200', 'Zapłacone'])
        ->and(array_slice($rows[3], 3))->toBe(['Odwołana', '0', 'Nie naliczono'])
        ->and($contents)->not->toContain('no_show');
});

test('the file holds the range and this trainer only', function () {
    $theirs = Client::factory()->for(User::factory()->create(), 'trainer')->create(['name' => 'Cudzy Klient']);
    TrainingSession::factory()->for($theirs)->on('2026-09-09')->create();

    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['service' => 'Wrześniowa']);
    TrainingSession::factory()->for($this->client)->on('2026-08-09')->create(['service' => 'Sierpniowa']);
    TrainingSession::factory()->for($this->client)->on('2026-09-10')->create(['service' => 'Usunięta'])->delete();

    $file = $this->export->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'));

    expect($file->rows)->toBe(1)
        ->and($file->contents)->toContain('Wrześniowa')
        ->not->toContain('Sierpniowa')
        ->not->toContain('Usunięta')
        ->not->toContain('Cudzy Klient');
});

test('a year is a range like any other', function () {
    TrainingSession::factory()->for($this->client)->on('2026-01-09')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create();
    TrainingSession::factory()->for($this->client)->on('2025-09-09')->create();

    $file = $this->export->forTrainer($this->trainer, DateRange::fromPrefix('2026'));

    expect($file->rows)->toBe(2)
        ->and($file->name)->toBe('samtrening-2026-maciej.csv');
});

test('the studio file names the trainer of every session', function () {
    $kasia = User::factory()->create(['name' => 'Katarzyna Samborska']);
    $herClient = Client::factory()->for($kasia, 'trainer')->create(['name' => 'Aleksander Górski']);

    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create();
    TrainingSession::factory()->for($herClient)->on('2026-09-10')->create();

    $file = $this->export->forStudio($this->trainer, DateRange::fromPrefix('2026-09'));

    $rows = csvRows($file->contents);

    expect($file->name)->toBe('samtrening-studio-2026-09.csv')
        ->and($file->rows)->toBe(2)
        ->and($rows[0])->toBe(['Data', 'Klient', 'Trener', 'Usługa', 'Rodzaj', 'Kwota PLN', 'Status płatności'])
        ->and(array_slice($rows[1], 0, 3))->toBe(['2026-09-09', 'Magdalena Wróbel', 'Maciej Samborski'])
        ->and(array_slice($rows[2], 0, 3))->toBe(['2026-09-10', 'Aleksander Górski', 'Katarzyna Samborska']);
});

test('every export leaves a line in the log', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->count(2)->create();

    $this->export->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'));

    expect(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Wyeksportował CSV')
        ->context->toBe('Wrzesień 2026 · 2 wiersze')
        ->actor_name->toBe('Maciej Samborski');
});

test('the button on the earnings screen hands the file over', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create();

    Livewire::actingAs($this->trainer)->test(Earnings::class)
        ->call('exportCsv')
        ->assertFileDownloaded('samtrening-2026-09-maciej.csv')
        ->assertDispatched('toast', message: 'Eksport CSV — Wrzesień 2026, 1 wiersz.');
});
