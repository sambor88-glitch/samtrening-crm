<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Actions\BlockTrainer;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use App\Domain\Team\Notifications\ResetPasswordNotification;
use App\Domain\Team\Notifications\TrainerInvitation;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Admin\TeamList;
use App\Livewire\Dialogs\InviteTrainerDialog;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
});

test('the studio list shows every account with its numbers', function () {
    $kasia = User::factory()->create(['name' => 'Katarzyna Samborska', 'specialty' => 'Zdrowa ciąża']);
    $client = Client::factory()->for($kasia, 'trainer')->create();
    TrainingSession::factory()->for($client)->on(now()->toDateString())->create(['price' => 20000]);

    User::factory()->invited()->create(['name' => 'Bartek Nowak']);

    $this->actingAs($this->owner)
        ->get(route('admin.trainers.index'))
        ->assertOk()
        ->assertSee('Trzy osoby.')
        ->assertSee('Katarzyna Samborska')
        ->assertSee('Zdrowa ciąża')
        ->assertSee('Aktywny')
        ->assertSee('200 zł')
        ->assertSee('Bartek Nowak')
        ->assertSee('Zaproszenie wysłane')
        ->assertSee('właściciel');
});

test('a trainer never sees this screen', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.trainers.index'))
        ->assertForbidden();
});

test('inviting creates an account that owns nothing and can see nothing yet', function () {
    Notification::fake();

    Livewire::actingAs($this->owner)->test(InviteTrainerDialog::class)
        ->dispatch('invite-trainer')
        ->set('name', 'Katarzyna Samborska')
        ->set('email', 'kasia@samtrening.com')
        ->set('specialty', 'Zdrowa ciąża')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('trainer-invited')
        ->assertDispatched('toast', message: 'Zaproszenie poszło na kasia@samtrening.com. Link ważny 7 dni.');

    $invited = User::query()->where('email', 'kasia@samtrening.com')->sole();

    expect($invited->status)->toBe(UserStatus::Invited)
        ->and($invited->password)->toBeNull()
        ->and($invited->is_owner)->toBeFalse();

    Notification::assertSentTo($invited, TrainerInvitation::class);

    expect(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Zaprosił trenera')
        ->context->toBe('Katarzyna Samborska · kasia@samtrening.com');
});

test('an address that already has an account is refused', function () {
    User::factory()->create(['email' => 'kasia@samtrening.com']);

    Livewire::actingAs($this->owner)->test(InviteTrainerDialog::class)
        ->dispatch('invite-trainer')
        ->set('name', 'Katarzyna Samborska')
        ->set('email', 'kasia@samtrening.com')
        ->call('save')
        ->assertHasErrors(['email' => 'Ten adres już ma konto w studiu.']);
});

test('a trainer cannot invite anybody', function () {
    Livewire::actingAs(User::factory()->create())->test(InviteTrainerDialog::class)
        ->dispatch('invite-trainer')
        ->assertForbidden();
});

test('resending replaces the previous link', function () {
    Notification::fake();

    $invited = User::factory()->invited()->create(['name' => 'Bartek Nowak']);

    Livewire::actingAs($this->owner)->test(TeamList::class)
        ->call('resendInvitation', $invited->id)
        ->assertDispatched('toast', message: 'Nowe zaproszenie poszło na '.$invited->email.'. Poprzedni link przestał działać.');

    Notification::assertSentTo($invited, TrainerInvitation::class);

    expect(ActivityEntry::query()->orderByDesc('id')->first()->action)->toBe('Ponowił zaproszenie');
});

test('blocking a trainer logs them out and stops the next request', function () {
    $kasia = User::factory()->create(['name' => 'Katarzyna Samborska']);

    $this->actingAs($kasia)->get(route('dashboard'))->assertOk();

    Livewire::actingAs($this->owner)->test(TeamList::class)
        ->call('toggleBlock', $kasia->id, true)
        ->assertDispatched('toast', message: 'Katarzyna Samborska zablokowany. Wylogowany ze wszystkich urządzeń.');

    expect($kasia->fresh()->status)->toBe(UserStatus::Blocked);

    // The blocked trainer's own session is over on the very next request.
    $this->actingAs($kasia->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('the owner account cannot be blocked, not even by calling the action', function () {
    // Two layers say no: the policy stops the screen…
    Livewire::actingAs($this->owner)->test(TeamList::class)
        ->call('toggleBlock', $this->owner->id, true)
        ->assertForbidden();

    // …and the action refuses even when called straight, which is what the AC asks for.
    expect(fn () => app(BlockTrainer::class)->handle($this->owner, $this->owner->fresh(), true))
        ->toThrow(RuntimeException::class, 'Konta właściciela nie da się zablokować.');

    expect($this->owner->fresh()->status)->toBe(UserStatus::Active);
});

test('unblocking gives the account back', function () {
    $blocked = User::factory()->blocked()->create(['name' => 'Bartek Nowak']);

    Livewire::actingAs($this->owner)->test(TeamList::class)
        ->call('toggleBlock', $blocked->id, false)
        ->assertDispatched('toast', message: 'Bartek Nowak znowu ma dostęp.');

    expect($blocked->fresh()->status)->toBe(UserStatus::Active)
        ->and(ActivityEntry::query()->orderByDesc('id')->first()->action)->toBe('Odblokował trenera');
});

test('resetting a password sends the trainer a link instead of setting one', function () {
    Notification::fake();

    $kasia = User::factory()->create(['name' => 'Katarzyna Samborska']);

    Livewire::actingAs($this->owner)->test(TeamList::class)
        ->call('resetPassword', $kasia->id)
        ->assertDispatched('toast', message: 'Link do ustawienia hasła poszedł na '.$kasia->email.'.');

    Notification::assertSentTo($kasia, ResetPasswordNotification::class);

    expect(ActivityEntry::query()->orderByDesc('id')->first()->action)->toBe('Zresetował hasło trenera');
});

test('the link preview shows the trainer\'s screen without touching their token', function () {
    $invited = User::factory()->invited()->create(['name' => 'Bartek Nowak']);

    $this->actingAs($this->owner)
        ->get(route('admin.trainers.preview', $invited))
        ->assertOk()
        ->assertSee('Witaj w studio, Bartek.')
        ->assertSee('Podgląd — dokładnie to widzi zaproszony trener.')
        ->assertSee('Zobowiązuję się do ochrony danych klientów');
});
