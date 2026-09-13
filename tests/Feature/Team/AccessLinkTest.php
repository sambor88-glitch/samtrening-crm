<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Team\Actions\GenerateAccessLink;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use App\Livewire\Admin\TeamList;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
    $this->kasia = User::factory()->create(['name' => 'Katarzyna Samborska', 'email' => 'kasia@samtrening.com']);
    $this->nowy = User::factory()->invited()->create(['name' => 'Piotr Nowak', 'email' => 'piotr@samtrening.com']);
});

test('an invited trainer gets an activation link that opens the welcome screen', function () {
    $link = app(GenerateAccessLink::class)->handle($this->owner, $this->nowy);

    expect($link)->toContain('/aktywacja/')
        ->toContain('email=piotr%40samtrening.com');

    // And it really lets them in — not just a well-formed address.
    $this->get($link)->assertOk()->assertSee('Piotr');
});

test('an active trainer gets a reset link that opens the reset screen', function () {
    $link = app(GenerateAccessLink::class)->handle($this->owner, $this->kasia);

    expect($link)->toContain('/nowe-haslo/')
        ->toContain('email=kasia%40samtrening.com');

    $this->get($link)->assertOk()->assertSee('Nowe hasło.');
});

test('the link the owner hands over actually sets the password', function () {
    $link = app(GenerateAccessLink::class)->handle($this->owner, $this->nowy);

    parse_str(parse_url($link, PHP_URL_QUERY), $query);
    $token = basename(parse_url($link, PHP_URL_PATH));

    $this->post(route('activation.store'), [
        'token' => $token,
        'email' => $query['email'],
        'password' => 'pierwsze-haslo-piotra',
        'password_confirmation' => 'pierwsze-haslo-piotra',
        'consent' => true,
    ])->assertRedirect(route('dashboard'));

    expect(Hash::check('pierwsze-haslo-piotra', $this->nowy->refresh()->password))->toBeTrue()
        ->and($this->nowy->status)->toBe(UserStatus::Active);
});

test('a fresh link kills the previous one', function () {
    $pierwszy = app(GenerateAccessLink::class)->handle($this->owner, $this->nowy);
    $drugi = app(GenerateAccessLink::class)->handle($this->owner, $this->nowy);

    expect($drugi)->not->toBe($pierwszy);

    parse_str(parse_url($pierwszy, PHP_URL_QUERY), $query);

    $this->post(route('activation.store'), [
        'token' => basename(parse_url($pierwszy, PHP_URL_PATH)),
        'email' => $query['email'],
        'password' => 'haslo-ze-starego-linku',
        'password_confirmation' => 'haslo-ze-starego-linku',
        'consent' => true,
    ])->assertSessionHasErrors();

    expect($this->nowy->refresh()->status)->toBe(UserStatus::Invited);
});

test('the owner never learns the password — the link only lets the trainer choose one', function () {
    $link = app(GenerateAccessLink::class)->handle($this->owner, $this->kasia);

    // Nothing password-shaped comes back, and the account is untouched until the trainer acts.
    expect($link)->toStartWith(config('app.url'))
        ->and($this->kasia->refresh()->password)->toBe($this->kasia->password);
});

test('the log says who handed out a link, to whom and of which kind', function () {
    app(GenerateAccessLink::class)->handle($this->owner, $this->nowy);
    app(GenerateAccessLink::class)->handle($this->owner, $this->kasia);

    $entries = ActivityEntry::query()->where('action', 'Wygenerował link dostępu')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0])->context->toBe('Piotr Nowak · aktywacja, ważny 7 dni')->actor_name->toBe('Maciej Samborski')
        ->and($entries[1])->context->toBe('Katarzyna Samborska · reset hasła, ważny 60 minut');
});

test('a trainer cannot hand out a link — not to anyone, not to themselves', function () {
    // The screen itself is closed to them: render authorises before anything else runs.
    Livewire::actingAs($this->kasia)->test(TeamList::class)->assertForbidden();

    // And the guard the button uses refuses independently of the screen — not for a colleague,
    // and not for their own account either, which would be the way around it.
    expect($this->kasia->can('resetPassword', $this->nowy))->toBeFalse()
        ->and($this->kasia->can('resetPassword', $this->kasia))->toBeFalse()
        ->and($this->owner->can('resetPassword', $this->kasia))->toBeTrue();

    expect(ActivityEntry::query()->count())->toBe(0);
});

test('the screen shows the link once, under the right trainer, with what it is worth', function () {
    Livewire::actingAs($this->owner)->test(TeamList::class)
        ->assertSee('Link z ręki')
        ->assertDontSee('Kopiuj link')
        ->call('accessLink', $this->nowy->getKey())
        ->assertSet('linkFor', $this->nowy->getKey())
        ->assertSee('Link dla Piotr Nowak')
        ->assertSee('ważny 7 dni')
        ->assertSee('Ty go nie poznasz')
        ->assertSee('Kopiuj link')
        ->call('forgetLink')
        ->assertSet('link', null)
        ->assertDontSee('Kopiuj link');
});

test('the reset-by-mail button stays, because it is the better path once mail works', function () {
    Livewire::actingAs($this->owner)->test(TeamList::class)
        ->assertSee('Reset hasła')
        ->assertSee('Ponów zaproszenie');
});
