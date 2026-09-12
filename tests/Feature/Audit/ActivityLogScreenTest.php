<?php

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Models\User;
use App\Livewire\Admin\ActivityLog;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
    $this->kasia = User::factory()->create(['name' => 'Katarzyna Samborska']);
});

test('the log shows when, who, what and the context', function () {
    $this->travelTo(Carbon::parse('2026-09-11 18:42', 'Europe/Warsaw'));
    app(ActivityLogger::class)->record($this->kasia, 'Wbiła sesję', 'Magdalena Wróbel · 200 zł · na saldo');

    $this->actingAs($this->owner)
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertSee('Kto co zmienił.')
        ->assertSeeInOrder(['Kiedy', 'Kto', 'Co się stało', 'Kontekst'])
        ->assertSee('11.09.2026 · 18:42')
        ->assertSee('Katarzyna Samborska')
        ->assertSee('Wbiła sesję')
        ->assertSee('Magdalena Wróbel · 200 zł · na saldo');
});

test('the newest entry sits on top', function () {
    $logger = app(ActivityLogger::class);

    $this->travelTo(Carbon::parse('2026-09-10 09:00', 'Europe/Warsaw'));
    $logger->record($this->kasia, 'Zmienił stawkę', 'Magdalena Wróbel · 180 zł → 200 zł');

    $this->travelTo(Carbon::parse('2026-09-11 09:00', 'Europe/Warsaw'));
    $logger->record($this->owner, 'Zaprosił trenera', 'Piotr Nowak · piotr@samtrening.com');

    Livewire::actingAs($this->owner)->test(ActivityLog::class)
        ->assertSeeInOrder(['Zaprosił trenera', 'Zmienił stawkę']);
});

test('older entries wait behind a button instead of loading all at once', function () {
    $logger = app(ActivityLogger::class);

    foreach (range(1, 60) as $i) {
        $this->travelTo(Carbon::parse('2026-09-01 08:00', 'Europe/Warsaw')->addMinutes($i));
        $logger->record($this->kasia, 'Wbiła sesję', 'Klientka numer '.$i);
    }

    Livewire::actingAs($this->owner)->test(ActivityLog::class)
        ->assertSee('Klientka numer 60')
        ->assertSee('Klientka numer 11')
        ->assertDontSee('Klientka numer 10')
        ->assertSee('50 z 60')
        ->call('more')
        ->assertSee('Klientka numer 10')
        ->assertSee('Klientka numer 1')
        ->assertDontSee('Pokaż starsze');
});

test('an empty log says so instead of showing an empty table', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertSee('Log jest pusty')
        ->assertDontSee('Kontekst');
});

test('a trainer never reaches the log', function () {
    app(ActivityLogger::class)->record($this->owner, 'Zablokował trenera', 'Piotr Nowak · piotr@samtrening.com');

    $this->actingAs($this->kasia)->get(route('admin.activity.index'))->assertForbidden();

    Livewire::actingAs($this->kasia)->test(ActivityLog::class)->assertForbidden();
});

test('the screen offers no way to change or drop an entry, not even to the owner', function () {
    app(ActivityLogger::class)->record($this->kasia, 'Wbiła sesję', 'Magdalena Wróbel · 200 zł · na saldo');

    $html = $this->actingAs($this->owner)->get(route('admin.activity.index'))->getContent();

    expect($html)->not->toContain('wire:click="delete')
        ->not->toContain('wire:click="edit')
        // The component exposes exactly one call: paging further back.
        ->and(collect((new ReflectionClass(ActivityLog::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method) => $method->getDeclaringClass()->getName() === ActivityLog::class)
            ->map(fn (ReflectionMethod $method) => $method->getName())
            ->values()
            ->all())->toBe(['more', 'render']);

    // And no route touches the table either.
    expect(collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->methods()[0].' '.$route->uri())
        ->filter(fn (string $route) => str_contains($route, 'admin/log'))
        ->values()
        ->all())->toBe(['GET admin/log']);
});
