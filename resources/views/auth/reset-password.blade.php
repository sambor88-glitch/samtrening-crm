@php
    use App\Support\Plural;

    // Tryb resetu ekranu 3. Tryb zaproszenia (powitanie, zgoda, „Aktywuj konto →") dokłada SC-37.
    $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
@endphp

<x-guest-layout title="Nowe hasło" kicker="Odzyskiwanie dostępu">
    <x-slot:headline>Nowe hasło, stare znika.</x-slot:headline>
    <x-slot:lead>
        Link z maila jest ważny {{ Plural::of($minutes, 'minutę', 'minuty', 'minut') }} i działa raz.
        Stare hasło przestaje działać w chwili ustawienia nowego.
    </x-slot:lead>

    <h1 class="mb-1.5 text-[32px]">Nowe hasło.</h1>
    <p class="mb-6 text-sm text-muted">Konto: {{ $request->email }}</p>

    <form method="POST" action="{{ route('password.store') }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <input type="hidden" name="email" value="{{ old('email', $request->email) }}">

        <div class="space-y-3.5">
            <x-input name="password" type="password" label="Nowe hasło" hint="Co najmniej 8 znaków." autocomplete="new-password" autofocus />
            <x-input name="password_confirmation" type="password" label="Powtórz hasło" autocomplete="new-password" />
        </div>

        @error('email')
            <p class="alert">{{ $message }}</p>
        @enderror

        <x-btn type="submit" variant="primary" block class="mt-5 px-3.5 py-2.5">Zapisz nowe hasło →</x-btn>
    </form>

    <x-btn variant="ghost" :href="route('login')" block class="mt-2.5 text-xs">← Wróć do logowania</x-btn>
</x-guest-layout>
