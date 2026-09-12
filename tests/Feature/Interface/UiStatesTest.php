<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Admin\StudioRoster;
use App\Livewire\Trainer\Payments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * docs/START-TUTAJ.md §11. Three families of state the prototype never had to show, checked
 * across the screens at once — a per-screen assertion would drift the moment a screen is added.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    Setting::query()->create(['reminders_enabled' => true, 'reminder_threshold_days' => 14]);

    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski', 'blik_number' => '600 100 200']);
    $this->client = Client::factory()->for($this->owner, 'trainer')->create(['name' => 'Magdalena Wróbel']);
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create(['price' => 20000]);
});

/**
 * Every wire:click in the Blade views, as file => the method it calls.
 *
 * @return array<int, array{file: string, call: string, line: string}>
 */
function clickHandlers(): array
{
    $calls = [];

    foreach (File::allFiles(resource_path('views/livewire')) as $file) {
        foreach (explode("\n", $file->getContents()) as $line) {
            if (preg_match('/wire:click="([a-zA-Z]+)/', $line, $found)) {
                $calls[] = [
                    'file' => $file->getRelativePathname(),
                    'call' => $found[1],
                    'line' => trim($line),
                ];
            }
        }
    }

    return $calls;
}

test('every button that writes or sends is disabled while the request is in flight', function () {
    // Buttons that only change what is displayed do not need it: nothing is written, and a
    // disabled filter would be in the way rather than a safeguard.
    $reading = ['close', 'more', 'show'];

    $unguarded = collect(clickHandlers())
        ->reject(fn (array $handler) => in_array($handler['call'], $reading, true))
        ->reject(function (array $handler) {
            $view = File::get(resource_path('views/livewire/'.$handler['file']));

            // The guard may sit on the same line or on one of the next few of the same tag.
            $position = strpos($view, $handler['line']);
            $tag = substr($view, $position, 600);

            return str_contains($tag, 'wire:loading.attr="disabled"');
        })
        ->map(fn (array $handler) => $handler['file'].' → '.$handler['call'])
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});

test('the tables that reload behind a filter put a skeleton in their own place', function () {
    $tables = [
        'trainer/client-list.blade.php',
        'trainer/session-list.blade.php',
        'trainer/earnings.blade.php',
        'admin/studio-roster.blade.php',
    ];

    foreach ($tables as $view) {
        $html = File::get(resource_path('views/livewire/'.$view));

        expect($html)->toContain('<x-skeleton-rows')
            // Delayed, so a fast answer never flashes a skeleton at anybody.
            ->toContain('wire:loading.delay')
            // As many rows as are on screen now: the height stays, the layout does not jump.
            ->toContain('wire:loading.delay.remove');
    }
});

test('nothing in the app reaches for a browser alert or confirm', function () {
    foreach (File::allFiles(resource_path('views')) as $file) {
        expect($file->getContents())
            ->not->toContain('alert(')
            ->not->toContain('confirm(');
    }
});

test('an error arrives as a toast in the error variant, never as a silent no-op', function () {
    // No BLIK number on this trainer, so the message cannot go out.
    $trainer = User::factory()->create(['blik_number' => null]);
    $client = Client::factory()->for($trainer, 'trainer')->create(['phone' => '+48 600 300 400']);
    TrainingSession::factory()->for($client)->on('2026-09-02')->create(['price' => 20000]);

    Livewire::actingAs($trainer)->test(Payments::class)
        ->call('requestBlik', $client->id)
        ->assertDispatched('toast', variant: 'error');
});

test('every screen that can be empty says something of its own', function () {
    $fresh = User::factory()->create(['name' => 'Anna Zielińska']);

    $answers = [
        route('dashboard') => ['Zero zaległości.', 'Nic jeszcze nie wbite.'],
        route('clients.index') => ['Kartoteka jest pusta'],
        route('sessions.index') => ['Nic jeszcze nie wbite'],
        route('payments.index') => ['Nic nierozliczonego.'],
        route('earnings.index') => ['Pusto w tym zakresie'],
    ];

    foreach ($answers as $url => $expected) {
        $page = $this->actingAs($fresh)->get($url)->assertOk();

        foreach ($expected as $text) {
            $page->assertSee($text);
        }

        // The one thing an empty state must never say.
        $page->assertDontSee('Brak danych');
    }
});

test('the owner screens that can be empty say their own thing too', function () {
    // A filter that matches nobody is not the same silence as a studio with no clients at all.
    Livewire::actingAs($this->owner)->test(StudioRoster::class)
        ->set('search', 'nikt-taki-nie-istnieje')
        ->assertSee('Nikt nie pasuje')
        ->assertDontSee('Kartoteka studia jest pusta');

    TrainingSession::query()->update(['payment_status' => PaymentStatus::Paid]);

    $this->actingAs($this->owner)->get(route('admin.outstanding.index'))
        ->assertSee('Nic nie wisi')
        ->assertDontSee('Brak danych');

    $this->actingAs($this->owner)->get(route('admin.activity.index'))->assertSee('Log jest pusty');
});

/**
 * Every `<td>` inside an `<x-data-table>` block, as file => the cell's opening tag. The week
 * closer is a real grid that scrolls sideways rather than folding into cards, so it is not one
 * of these and needs no labels.
 *
 * @return array<int, array{file: string, cell: string}>
 */
function tableCells(): array
{
    $cells = [];

    foreach (File::allFiles(resource_path('views/livewire')) as $file) {
        $html = $file->getContents();

        preg_match_all('/<x-data-table.*?<\/x-data-table>/s', $html, $tables);

        foreach ($tables[0] as $table) {
            preg_match_all('/<td[^>]*>/', $table, $found);

            foreach ($found[0] as $cell) {
                $cells[] = ['file' => $file->getRelativePathname(), 'cell' => $cell];
            }
        }
    }

    return $cells;
}

test('every cell in every table carries the label its phone card will need', function () {
    $cells = tableCells();

    expect($cells)->not->toBeEmpty();

    $unlabelled = collect($cells)
        ->reject(fn (array $cell) => str_contains($cell['cell'], 'data-label='))
        ->map(fn (array $cell) => $cell['file'].' → '.$cell['cell'])
        ->values()
        ->all();

    expect($unlabelled)->toBe([]);
});

test('touch targets are 44 px where the pointer is a finger', function () {
    $css = File::get(resource_path('css/app.css'));

    // By pointer, not by width: a narrow window on a desktop is still driven with a mouse.
    expect($css)->toContain('@media (pointer: coarse)')
        ->and(preg_match('/@media \(pointer: coarse\) \{(.*?)\n    \}/s', $css, $block))->toBe(1)
        ->and($block[1])->toContain('min-height: 44px')
        ->and($block[1])->toContain('.btn');
});
