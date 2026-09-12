<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Settings\Actions\UpdateStudioRules;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\Payments;
use App\Livewire\Trainer\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    Setting::query()->create([
        'reminders_enabled' => true,
        'reminder_threshold_days' => 14,
        'free_cancellation_hours' => 24,
        'retention_months' => 60,
        'ticker_enabled' => true,
    ]);

    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => null]);
});

test('a trainer without a BLIK number sees an empty field that says what it is for', function () {
    $this->actingAs($this->trainer)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Twój numer BLIK')
        ->assertSee('Podstawia się w prośbie o płatność, monicie i podsumowaniu miesiąca.')
        ->assertSee('Ten sam numer, na który klient robi przelew BLIK.');

    Livewire::actingAs($this->trainer)->test(Settings::class)->assertSet('blik', '');
});

test('everybody sets their own number and nobody else touches it', function () {
    Livewire::actingAs($this->trainer)->test(Settings::class)
        ->set('blik', '600 100 200')
        ->call('saveBlik')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect($this->trainer->refresh()->blik_number)->toBe('600 100 200')
        ->and($this->owner->refresh()->blik_number)->toBeNull()
        ->and(ActivityEntry::query()->where('action', 'Zmienił numer BLIK')->first())
        ->context->toBe('brak → 600 100 200')
        ->actor_name->toBe('Katarzyna Samborska');
});

test('the BLIK field takes a phone number and refuses prose', function () {
    Livewire::actingAs($this->trainer)->test(Settings::class)
        ->set('blik', 'zapytaj Kasię')
        ->call('saveBlik')
        ->assertHasErrors(['blik' => 'regex']);

    expect($this->trainer->refresh()->blik_number)->toBeNull();
});

test('a trainer sees the studio rules read-only', function () {
    $page = $this->actingAs($this->trainer)->get(route('settings.index'))->assertOk();

    $page->assertSee('Zasady studia')
        // All three numbers are on the screen. The middle one once vanished: a straight quote
        // inside a double-quoted hint attribute closed the tag early.
        ->assertSee('Bezpłatne odwołanie (h)')
        ->assertSee('Monit po (dni)')
        ->assertSee('Retencja danych (mies.)')
        ->assertSee('tylko podgląd')
        ->assertSee('Zmienia je właściciel studia.')
        ->assertDontSee('Zapisz zasady');

    expect(substr_count($page->getContent(), 'disabled'))->toBeGreaterThanOrEqual(4);
});

test('a trainer cannot change the rules even by calling the action directly', function () {
    expect(fn () => app(UpdateStudioRules::class)->handle($this->trainer, ['reminder_threshold_days' => 3]))
        ->toThrow(AuthorizationException::class);

    Livewire::actingAs($this->trainer)->test(Settings::class)
        ->set('reminderThresholdDays', 3)
        ->call('saveRules')
        ->assertForbidden();

    expect(Setting::current()->reminder_threshold_days)->toBe(14);
});

test('the owner changes the rules and the log keeps the value before and after', function () {
    Livewire::actingAs($this->owner)->test(Settings::class)
        ->assertSee('Zapisz zasady')
        ->set('reminderThresholdDays', 7)
        ->set('freeCancellationHours', 12)
        ->set('remindersEnabled', false)
        ->call('saveRules')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Zasady studia zapisane. Obowiązują od zaraz.');

    $settings = Setting::current();

    expect($settings->reminder_threshold_days)->toBe(7)
        ->and($settings->free_cancellation_hours)->toBe(12)
        ->and($settings->reminders_enabled)->toBeFalse()
        ->and(ActivityEntry::query()->where('action', 'Zmienił zasady studia')->first()->context)
        ->toBe('monit o zaległej płatności: włączone → wyłączone, bezpłatne odwołanie (h): 24 → 12, monit po (dni): 14 → 7');
});

test('saving without changing anything writes nothing to the log', function () {
    Livewire::actingAs($this->owner)->test(Settings::class)->call('saveRules')->assertHasNoErrors();

    expect(ActivityEntry::query()->count())->toBe(0);
});

test('the rules refuse values that would make them meaningless', function () {
    Livewire::actingAs($this->owner)->test(Settings::class)
        ->set('reminderThresholdDays', 0)
        ->set('retentionMonths', 6)
        ->call('saveRules')
        ->assertHasErrors(['reminderThresholdDays', 'retentionMonths']);

    expect(Setting::current()->reminder_threshold_days)->toBe(14);
});

test('moving the threshold moves the "po terminie" marks on Payments right away', function () {
    $client = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Magdalena Wróbel']);
    TrainingSession::factory()->for($client)->on('2026-09-05')->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Payments::class)->assertDontSee('po terminie');

    Livewire::actingAs($this->owner)->test(Settings::class)
        ->set('reminderThresholdDays', 7)
        ->call('saveRules');

    Livewire::actingAs($this->trainer)->test(Payments::class)->assertSee('po terminie');
});

test('the RODO debt is the owner panel, not everybody-s', function () {
    $this->actingAs($this->owner)
        ->get(route('settings.index'))
        ->assertSee('Dług RODO')
        ->assertSee('Rejestr czynności przetwarzania')
        ->assertSee('Umowa powierzenia z dostawcą SMS')
        ->assertSee('Szyfrowanie danych o zdrowiu')
        ->assertSee('Czyszczenie kartotek po retencji')
        ->assertDontSee('Stripe');

    $this->actingAs($this->trainer)->get(route('settings.index'))->assertDontSee('Dług RODO');
});

test('the same screen answers in both panels', function () {
    $this->actingAs($this->owner)->get(route('admin.settings.index'))->assertOk()->assertSee('Jak to ma działać.');
    $this->actingAs($this->trainer)->get(route('admin.settings.index'))->assertForbidden();
});
