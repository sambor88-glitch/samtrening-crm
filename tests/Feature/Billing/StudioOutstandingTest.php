<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Admin\Outstanding;
use App\Livewire\Trainer\Reminders;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
    $this->kasia = User::factory()->create(['name' => 'Katarzyna Samborska']);
    $this->client = Client::factory()->for($this->kasia, 'trainer')->create(['name' => 'Magdalena Wróbel']);
});

test('the studio pile equals the sum of the rows below it', function () {
    TrainingSession::factory()->for($this->client)->on(now()->subDays(20)->toDateString())->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->create(['price' => 20000]);

    $mine = Client::factory()->for($this->owner, 'trainer')->create(['name' => 'Aleksander Górski']);
    TrainingSession::factory()->for($mine)->create(['price' => 22000]);

    $this->actingAs($this->owner)
        ->get(route('admin.outstanding.index'))
        ->assertOk()
        ->assertSee('Co wisi nieopłacone.')
        ->assertSee('620 zł')
        ->assertSee('Magdalena Wróbel')
        ->assertSee('Katarzyna Samborska')
        ->assertSee('2 sesje')
        ->assertSee('po terminie');
});

test('the nudge reaches the trainer who runs that client, not the client', function () {
    TrainingSession::factory()->for($this->client)->create(['price' => 20000]);

    Livewire::actingAs($this->owner)->test(Outstanding::class)
        ->call('nudge', $this->client->id)
        ->assertDispatched('toast', message: 'Katarzyna Samborska zobaczy przypomnienie o Magdalena Wróbel przy najbliższym wejściu.');

    expect($this->kasia->unreadNotifications()->count())->toBe(1)
        ->and($this->kasia->unreadNotifications()->first()->data['amount'])->toBe('200 zł')
        ->and($this->kasia->unreadNotifications()->first()->data['from'])->toBe('Maciej Samborski')
        ->and($this->owner->unreadNotifications()->count())->toBe(0);

    expect(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Przypomniał trenerowi')
        ->context->toBe('Katarzyna Samborska · Magdalena Wróbel · 200 zł');
});

test('the trainer sees it on the next visit and it does not come back once closed', function () {
    TrainingSession::factory()->for($this->client)->create(['price' => 20000]);

    Livewire::actingAs($this->owner)->test(Outstanding::class)->call('nudge', $this->client->id);

    $this->actingAs($this->kasia)
        ->get(route('dashboard'))
        ->assertSee('Przypomnienie od właściciela')
        ->assertSee('Magdalena Wróbel')
        ->assertSee('200 zł');

    $notification = $this->kasia->unreadNotifications()->first();

    Livewire::actingAs($this->kasia)->test(Reminders::class)
        ->call('dismiss', $notification->id)
        ->assertDontSee('Przypomnienie od właściciela');

    expect($this->kasia->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->kasia)->get(route('dashboard'))->assertDontSee('Przypomnienie od właściciela');
});

test('a trainer never opens the studio arrears screen', function () {
    $this->actingAs($this->kasia)->get(route('admin.outstanding.index'))->assertForbidden();
});

test('nothing outstanding says so', function () {
    Livewire::actingAs($this->owner)->test(Outstanding::class)
        ->assertSee('Nic nie wisi')
        ->assertSee('Wszystko rozliczone.');
});
