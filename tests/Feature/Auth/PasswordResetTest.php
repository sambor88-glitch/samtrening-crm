<?php

use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use App\Domain\Team\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Grabs the token out of the mail that was faked away.
 */
function resetToken(User $user): string
{
    $token = null;

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    return $token;
}

test('the reset screen asks for the address', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Reset hasła.')
        ->assertSee('Podaj adres, na który dostałeś zaproszenie.');
});

test('the answer is the same whether the address is in the studio or not', function () {
    Notification::fake();

    // Vite and Livewire print their tags only on the first render in a process, so the two
    // screens are compared without <head> and without scripts.
    $body = fn (string $html) => trim(preg_replace(
        ['#<head>.*?</head>|<script[^>]*>.*?</script>|<!--.*?-->#s', '#\s+#'],
        ['', ' '],
        $html
    ));

    $trainer = User::factory()->create();

    $this->post(route('password.email'), ['email' => $trainer->email])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors();
    $known = $this->get(route('password.request'))->getContent();

    $this->post(route('password.email'), ['email' => 'nikt@samtrening.com'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors();
    $unknown = $this->get(route('password.request'))->getContent();

    expect($body($unknown))->toBe($body($known))
        ->and($known)->toContain('Sprawdź skrzynkę.')
        ->and($known)->toContain('ważny <strong>60 minut</strong>');

    Notification::assertSentTo($trainer, ResetPasswordNotification::class);
    Notification::assertCount(1);
});

test('a blocked account gets no link and the same answer', function () {
    Notification::fake();

    $blocked = User::factory()->blocked()->create();

    $this->post(route('password.email'), ['email' => $blocked->email])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

test('the link sets a new password and burns out after one use', function () {
    Notification::fake();

    $trainer = User::factory()->create();
    $this->post(route('password.email'), ['email' => $trainer->email]);

    $token = resetToken($trainer);

    $this->get(route('password.reset', ['token' => $token, 'email' => $trainer->email]))
        ->assertOk()
        ->assertSee('Nowe hasło.');

    $form = [
        'token' => $token,
        'email' => $trainer->email,
        'password' => 'nowe-mocne-haslo',
        'password_confirmation' => 'nowe-mocne-haslo',
    ];

    // Since SC-37 the link logs the trainer straight in — screen 3 of the spec.
    $this->post(route('password.store'), $form)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    expect(Hash::check('nowe-mocne-haslo', $trainer->refresh()->password))->toBeTrue();

    auth()->logout();

    $this->post(route('password.store'), $form)->assertSessionHasErrors('email');
});

test('the link stops working after an hour', function () {
    Notification::fake();

    $trainer = User::factory()->create();
    $this->post(route('password.email'), ['email' => $trainer->email]);

    $token = resetToken($trainer);

    $this->travel(61)->minutes();

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $trainer->email,
        'password' => 'nowe-mocne-haslo',
        'password_confirmation' => 'nowe-mocne-haslo',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $trainer->refresh()->password))->toBeTrue();
});

test('an invited trainer who sets a password can log in', function () {
    Notification::fake();

    $invited = User::factory()->invited()->create();
    $this->post(route('password.email'), ['email' => $invited->email]);

    $token = resetToken($invited);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $invited->email,
        'password' => 'pierwsze-haslo-kasi',
        'password_confirmation' => 'pierwsze-haslo-kasi',
    ])->assertRedirect(route('dashboard'));

    expect($invited->refresh()->status)->toBe(UserStatus::Active);

    $this->assertAuthenticatedAs($invited);

    // And the password works on the normal login screen afterwards.
    auth()->logout();

    $this->post(route('login'), ['email' => $invited->email, 'password' => 'pierwsze-haslo-kasi'])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('the reset mail speaks Polish', function () {
    $mail = (new ResetPasswordNotification('token'))->toMail(User::factory()->create());

    expect($mail->subject)->toStartWith('Ustaw nowe hasło w ')
        ->and($mail->actionText)->toBe('Ustaw nowe hasło')
        ->and(collect($mail->introLines)->merge($mail->outroLines)->implode(' '))
        ->toContain('Link jest ważny 60 minut i działa jednorazowo.');
});
