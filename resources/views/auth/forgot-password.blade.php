@php
    use App\Support\Plural;

    // Konto ze statusem `invited` trafia tu z ekranu logowania — hasło ustawia się tylko z linku.
    $activation = (bool) session('activation');
    $sent = (bool) session('link_sent');
    $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
@endphp

<x-guest-layout
    :title="$activation ? 'Aktywacja konta' : 'Reset hasła'"
    :kicker="$activation ? 'Aktywacja konta' : 'Odzyskiwanie dostępu'"
>
    <x-slot:headline>
        {{ $activation ? 'Konto już na Ciebie czeka.' : 'Właściciel też o tym zapomina.' }}
    </x-slot:headline>
    <x-slot:lead>
        @if ($activation)
            Hasło ustawisz linkiem z maila — tylko tak mamy pewność, że konto przejmuje ten, do kogo należy adres.
        @else
            Nad kontem właściciela nie ma nikogo, kto by je odblokował — dlatego reset mailem działa dla każdego, bez proszenia kogokolwiek.
        @endif
    </x-slot:lead>

    @if ($sent)
        <h1 class="mb-1.5 text-[30px]">Sprawdź skrzynkę.</h1>
        <p class="mb-4 text-sm">
            Jeśli konto o tym adresie istnieje, poleciał na nie link do ustawienia nowego hasła.
            Link jest ważny <strong>{{ Plural::of($minutes, 'minutę', 'minuty', 'minut') }}</strong> i działa jednorazowo.
        </p>
        <p class="text-[13px] text-muted">
            Nie mówimy, czy adres jest w systemie — inaczej dałoby się sprawdzać, kto u nas pracuje.
        </p>

        <x-btn variant="ghost" :href="route('login')" block class="mt-5 text-xs">← Wróć do logowania</x-btn>
    @else
        <h1 class="mb-1.5 text-[32px]">{{ $activation ? 'Ustaw hasło.' : 'Reset hasła.' }}</h1>
        <p class="mb-6 text-sm text-muted">
            {{ $activation ? 'Wyślemy link, którym ustawisz hasło do swojego konta.' : 'Podaj adres, na który dostałeś zaproszenie.' }}
        </p>

        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf

            <x-input name="email" type="email" label="E-mail" placeholder="imie@samtrening.com" :value="old('email')" autocomplete="username" autofocus />

            <x-btn type="submit" variant="primary" block class="mt-4 px-3.5 py-2.5">Wyślij link →</x-btn>
        </form>

        <x-btn variant="ghost" :href="route('login')" block class="mt-2.5 text-xs">← Wróć do logowania</x-btn>

        <x-owner-contact class="mt-5">Nie masz dostępu do skrzynki? Poproś o reset bezpośrednio:</x-owner-contact>
    @endif
</x-guest-layout>
