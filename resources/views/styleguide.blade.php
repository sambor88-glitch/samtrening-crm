@php
    use App\Support\Money;
    use App\Support\Plural;
@endphp
<!DOCTYPE html>
<html lang="pl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Styleguide — {{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        {{-- The app chrome, as the owner sees it (a trainer gets no role switch). --}}
        @php($demoOwner = (new App\Domain\Team\Models\User(['name' => 'Maciej Samborski']))->forceFill(['is_owner' => true]))
        <x-layout.top-bar :user="$demoOwner" />
        <x-layout.app-nav :navigation="App\View\Components\AppLayout::TRAINER_NAVIGATION" />
        <x-layout.ticker />

        <main class="mx-auto max-w-[1240px] space-y-12 px-4 pt-8 pb-20">
            <section>
                <p class="mb-2 text-[11px] tracking-[0.16em] text-accent uppercase">Styleguide · tylko lokalnie</p>
                <h1>Wbijasz sesję. Reszta liczy się sama.</h1>
                <h1 class="text-[54px]">Ś Ć Ę Ż Ź Ł Ó Ń Ą — ogonki całe</h1>
                <h2>Nagłówek h2</h2>
                <h3>Nagłówek h3</h3>
                <h4>Nagłówek h4</h4>
                <h5>Nagłówek h5</h5>
                <h6>Nagłówek h6</h6>
                <p class="max-w-[640px]">Grafik zostaje w Google Calendar. Tutaj wbijasz fakty — stawkę ustalasz sam, całość idzie do Ciebie.</p>
                <p class="text-muted">Tekst pomocniczy w kolorze muted.</p>
                <hr class="hr">
            </section>

            <section class="space-y-3">
                <h6>Przyciski</h6>
                <div class="flex flex-wrap items-center gap-3">
                    <x-btn variant="primary">＋ Wbij sesję</x-btn>
                    <x-btn>Edytuj kartę</x-btn>
                    <x-btn variant="ghost">Karta →</x-btn>
                    <x-btn variant="primary" disabled>Zapisuję…</x-btn>
                </div>
                <div class="max-w-[380px]">
                    <x-btn variant="primary" block>Zaloguj się →</x-btn>
                </div>
            </section>

            <section class="space-y-3">
                <h6>Tagi</h6>
                <div class="flex flex-wrap gap-2">
                    <x-tag variant="accent">Odwołana · naliczone</x-tag>
                    <x-tag variant="accent-2">Zgoda rodzica</x-tag>
                    <x-tag>Zapłacone</x-tag>
                    <x-tag variant="outline">Poproszono</x-tag>
                </div>
            </section>

            <section class="grid max-w-[720px] gap-4 sm:grid-cols-2">
                <h6 class="sm:col-span-2">Formularze</h6>
                <x-input name="email" type="email" label="E-mail" placeholder="imie@samtrening.com" />
                <x-input name="price" type="number" step="5" label="Kwota (zł)" value="200" hint="Stawka klienta: 200 zł" />
                <div class="field sm:col-span-2">
                    <label for="note">Notatka z sesji</label>
                    <textarea id="note" class="input" placeholder="Co się działo na treningu"></textarea>
                </div>
                <p class="alert sm:col-span-2">Nie znamy tego adresu. Reset hasła tu nie pomoże — konto musi założyć właściciel studia.</p>
                <div class="flex flex-wrap gap-4 sm:col-span-2">
                    <label class="radio"><input type="radio" name="charge" checked><span class="dot"></span>Nie naliczam</label>
                    <label class="radio"><input type="radio" name="charge"><span class="dot"></span>Naliczam — na saldo</label>
                </div>
                <div class="sm:col-span-2">
                    <x-seg name="filter" :options="['active' => 'Aktywni', 'balance' => 'Z saldem', 'online' => 'Online', 'archive' => 'Archiwum']" value="active" />
                </div>
            </section>

            <section class="space-y-3">
                <h6>Pasek statystyk</h6>
                <x-stat-bar :items="[
                    ['label' => 'Sesje we wrześniu', 'value' => '38', 'hint' => 'tylko odbyte'],
                    ['label' => 'Zarobek — wrzesień', 'value' => Money::format(760000), 'hint' => '100% Twoje'],
                    ['label' => 'Nierozliczone', 'value' => Money::format(125000), 'hint' => Plural::of(3, 'klient', 'klientów', 'klientów').' z saldem'],
                    ['label' => 'Aktywni klienci', 'value' => '12'],
                ]" />
            </section>

            <section class="space-y-3">
                <h6>Tabela (poniżej 760 px — karty)</h6>
                <x-data-table :columns="['Klient', 'Charakterystyka', 'Ostatnia sesja', 'Stawka', 'Saldo', '']">
                    <tr>
                        <td data-label="Klient"><div><div class="font-extrabold">Magdalena Wróbel</div><div class="text-xs opacity-55">+48 600 100 200</div></div></td>
                        <td data-label="Charakterystyka"><x-tag variant="accent">Zdrowa ciąża</x-tag></td>
                        <td data-label="Ostatnia sesja">09.09</td>
                        <td data-label="Stawka">{{ Money::format(20000) }}</td>
                        <td data-label="Saldo" class="font-extrabold text-accent-700">{{ Money::format(40000) }}</td>
                        <td data-label=""><x-btn variant="ghost">Karta →</x-btn></td>
                    </tr>
                    <tr>
                        <td data-label="Klient"><div><div class="font-extrabold">Aleksander Górski</div><div class="text-xs opacity-55">+48 600 300 400</div></div></td>
                        <td data-label="Charakterystyka"><x-tag>Siła</x-tag></td>
                        <td data-label="Ostatnia sesja">08.09</td>
                        <td data-label="Stawka">{{ Money::format(22000) }}</td>
                        <td data-label="Saldo">—</td>
                        <td data-label=""><x-btn variant="ghost">Karta →</x-btn></td>
                    </tr>
                </x-data-table>
            </section>

            <section class="grid gap-6 md:grid-cols-2">
                <div>
                    <h6>Pusty stan</h6>
                    <x-empty-state title="Brak klientów">
                        Kartoteka jest pusta. Dodaj pierwszego klienta — potem wbijesz mu sesję.
                        <x-slot:action><x-btn variant="primary">＋ Dodaj klienta</x-btn></x-slot:action>
                    </x-empty-state>
                </div>
                <div>
                    <h6>Wczytywanie</h6>
                    <x-skeleton-rows :rows="5" :height="44" class="mt-5" />
                </div>
            </section>

            <section class="space-y-3">
                <h6>Toast</h6>
                <div class="flex flex-wrap gap-3">
                    <x-btn onclick="window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Sesja zapisana. Saldo: 400 zł.' } }))">Sukces</x-btn>
                    <x-btn onclick="window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Wiadomość nie wyszła — sesja jest zapisana.', variant: 'error' } }))">Błąd</x-btn>
                    <x-btn onclick="window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Usunięto sesję z 09.09.', action: { label: 'Cofnij', event: 'styleguide-undo' } } }))">Z „Cofnij”</x-btn>
                </div>
            </section>
        </main>

        <x-toast />

        @livewireScripts
    </body>
</html>
