<x-guest-layout title="Logowanie" kicker="CRM studia">
    <x-slot:headline>Wbijasz sesję.<br>Reszta liczy się sama.</x-slot:headline>
    <x-slot:lead>Kartoteka, salda i zarobki jednego trenera. Bez pakietów, bez prowizji, bez Excela.</x-slot:lead>

    <h1 class="mb-1.5 text-[34px]">Zaloguj się.</h1>
    <p class="mb-6 text-sm text-muted">Konto zakłada właściciel studia.</p>

    @if (session('status'))
        <p class="alert mb-4">{{ session('status') }}</p>
    @endif

    {{-- novalidate: wszystkie cztery komunikaty z §Ekran 1 pisze serwer, nie dymek przeglądarki. --}}
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="space-y-3.5">
            <x-input name="email" type="email" label="E-mail" placeholder="imie@samtrening.com" :value="old('email')" autocomplete="username" autofocus />
            <x-input name="password" type="password" label="Hasło" placeholder="••••••••" autocomplete="current-password" />
        </div>

        <x-btn type="submit" variant="primary" block class="mt-5 px-3.5 py-2.5">Zaloguj się →</x-btn>
    </form>

    <x-btn variant="ghost" :href="route('password.request')" block class="mt-2.5 text-xs">Nie pamiętam hasła</x-btn>

    <x-owner-contact class="mt-5">Problem z dostępem albo konto zablokowane?</x-owner-contact>
</x-guest-layout>
