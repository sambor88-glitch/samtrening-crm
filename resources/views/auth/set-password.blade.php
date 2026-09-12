@php
    use Illuminate\Support\Str;

    $invitation = $mode === 'invitation';
    $firstName = $account ? Str::before($account->name, ' ') : null;
@endphp

<x-guest-layout
    :title="$invitation ? 'Aktywacja konta' : 'Nowe hasło'"
    :variant="$invitation ? 'accent' : 'surface'"
    :kicker="$invitation ? 'Zaproszenie do zespołu' : 'Nowe hasło'"
>
    <x-slot:headline>
        @if ($invitation)
            {{ $firstName ? 'Witaj w studio, '.$firstName.'.' : 'Witaj w studio.' }}
        @else
            {{ $firstName ? 'Wracamy do gry, '.$firstName.'.' : 'Wracamy do gry.' }}
        @endif
    </x-slot:headline>

    <x-slot:lead>
        @if ($invitation)
            Ustaw hasło i konto jest Twoje. Zobaczysz tylko swoich klientów — nikt poza właścicielem
            studia nie zagląda w Twoje notatki.
        @else
            Stare hasło przestaje działać w chwili ustawienia nowego, a pozostałe urządzenia zostaną
            wylogowane.
        @endif
    </x-slot:lead>

    <h1 class="mb-1.5 text-[32px]">{{ $invitation ? 'Ustaw hasło.' : 'Nowe hasło.' }}</h1>
    <p class="mb-6 text-sm text-muted">
        {{ $invitation ? 'Link ważny 7 dni' : 'Link ważny 60 minut · jednorazowy' }}
    </p>

    @if ($account)
        <div class="mb-5 bg-surface p-3.5">
            <p class="text-[10px] tracking-[0.14em] text-accent uppercase">
                {{ $invitation ? 'Zaproszenie dla' : 'Konto' }}
            </p>
            <p class="mt-1 font-extrabold">{{ $account->name }}</p>
            <p class="text-xs opacity-60">{{ $account->email }}</p>
            @if ($account->specialty)
                <p class="text-xs opacity-60">{{ $account->specialty }}</p>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ $invitation ? route('activation.store') : route('password.store') }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ old('email', $email) }}">

        <div class="space-y-3.5">
            <x-input name="password" type="password" label="Hasło" hint="Minimum 8 znaków."
                     autocomplete="new-password" autofocus />
            <x-input name="password_confirmation" type="password" label="Powtórz hasło"
                     autocomplete="new-password" />
        </div>

        @if ($invitation)
            <x-check class="mt-3.5" name="consent" value="1"
                     label="Zobowiązuję się do ochrony danych klientów"
                     hint="Kontuzje, przeciwwskazania i ciąża to szczególna kategoria danych. Nie wynosisz ich poza CRM."
                     :checked="old('consent')" />

            @error('consent')
                <p class="alert">{{ $message }}</p>
            @enderror
        @endif

        @error('email')
            <p class="alert">{{ $message }}</p>
        @enderror

        <x-btn type="submit" variant="primary" block class="mt-5 px-3.5 py-2.5">
            {{ $invitation ? 'Aktywuj konto →' : 'Zapisz nowe hasło →' }}
        </x-btn>
    </form>

    <x-btn variant="ghost" :href="route('login')" block class="mt-2.5 text-xs">← Wróć do logowania</x-btn>
</x-guest-layout>
