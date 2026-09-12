<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">Jak to ma działać.</h1>
    <p class="mt-2 max-w-[58ch] text-muted">
        Numer BLIK jest Twój — wpłaty idą wprost do Ciebie. Zasady studia są wspólne i zmienia je
        właściciel, bo dotyczą wszystkich kartotek naraz.
    </p>

    <hr class="hr">

    <div class="grid border-y-2 border-divider [grid-template-columns:repeat(auto-fit,minmax(300px,1fr))]">
        <section class="border-l border-divider px-5 py-6">
            <h3 class="mb-1">Twój numer BLIK</h3>
            <p class="mb-4 text-[13px] text-muted">
                Podstawia się w prośbie o płatność, monicie i podsumowaniu miesiąca. Bez niego
                żadna z tych wiadomości nie wyjdzie.
            </p>

            <x-input name="blik" label="Numer telefonu do BLIK-a" wire:model="blik"
                     placeholder="600 100 200" autocomplete="off"
                     hint="Ten sam numer, na który klient robi przelew BLIK." />

            <x-btn variant="primary" class="mt-3" wire:click="saveBlik"
                   wire:loading.attr="disabled" wire:target="saveBlik">
                <span wire:loading.remove wire:target="saveBlik">Zapisz numer</span>
                <span wire:loading wire:target="saveBlik">Zapisuję…</span>
            </x-btn>
        </section>

        <section class="border-l border-divider px-5 py-6">
            <div class="mb-1 flex flex-wrap items-baseline gap-2">
                <h3>Zasady studia</h3>
                @unless ($canManage)
                    <x-tag variant="outline">tylko podgląd</x-tag>
                @endunless
            </div>
            <p class="mb-4 text-[13px] text-muted">
                @if ($canManage)
                    Każda zmiana zapisuje się w logu razem z wartością przed i po.
                @else
                    Zmienia je właściciel studia. Widzisz, co obowiązuje.
                @endif
            </p>

            <x-check label="Monit o zaległej płatności"
                     hint="Codzienny przebieg wysyła SMS klientom po terminie."
                     wire:model="remindersEnabled" :disabled="! $canManage" />

            <div class="mt-4 grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(130px,1fr))]">
                <x-input name="freeCancellationHours" type="number" min="0" max="168"
                         label="Bezpłatne odwołanie (h)" wire:model="freeCancellationHours"
                         :disabled="! $canManage" hint="Opis w oknie wbijania sesji." />

                <x-input name="reminderThresholdDays" type="number" min="1" max="365"
                         label="Monit po (dni)" wire:model="reminderThresholdDays"
                         :disabled="! $canManage" hint='Oznaczenie „po terminie" w Płatnościach.' />

                <x-input name="retentionMonths" type="number" min="12" max="600" step="12"
                         label="Retencja danych (mies.)" wire:model="retentionMonths"
                         :disabled="! $canManage" hint="Treść okna usuwania danych." />
            </div>

            <div class="mt-4">
                <x-check label="Pasek z hasłami na górze"
                         hint="Wyłącz, jeśli rozprasza."
                         wire:model="tickerEnabled" :disabled="! $canManage" />
            </div>

            @if ($canManage)
                <x-btn variant="primary" class="mt-5" wire:click="saveRules"
                       wire:loading.attr="disabled" wire:target="saveRules">
                    <span wire:loading.remove wire:target="saveRules">Zapisz zasady</span>
                    <span wire:loading wire:target="saveRules">Zapisuję…</span>
                </x-btn>
            @endif
        </section>
    </div>

    @if ($owner)
        <section class="mt-8 border-t-2 border-accent bg-surface p-5">
            <h3>Dług RODO</h3>
            <p class="mt-1 max-w-[62ch] text-[13px] text-muted">
                Czego system nie załatwi za Ciebie. Dane o zdrowiu klientów to art. 9 RODO —
                przy kontroli pyta się o papiery, nie o kod.
            </p>

            <ul class="mt-4 grid gap-x-8 [grid-template-columns:repeat(auto-fit,minmax(290px,1fr))]">
                @foreach ([
                    ['Rejestr czynności przetwarzania', 'Do spisania poza systemem.', false],
                    ['Umowa powierzenia z dostawcą SMS', 'Do podpisania przy wyborze dostawcy (SC-16).', false],
                    ['Umowy powierzenia z trenerami', 'Jeśli pracują na własnych działalnościach.', false],
                    ['Szyfrowanie danych o zdrowiu', 'Przeciwwskazania są szyfrowane w bazie.', true],
                    ['Czyszczenie kartotek po retencji', 'Automat dochodzi w SC-46.', false],
                ] as [$title, $note, $done])
                    <li class="flex gap-3 border-b border-divider py-3">
                        <span @class([
                            'mt-0.5 text-sm font-extrabold',
                            'text-accent' => $done,
                            'opacity-40' => ! $done,
                        ])>{{ $done ? '■' : '□' }}</span>
                        <span>
                            <span class="block text-sm font-extrabold">{{ $title }}</span>
                            <span class="block text-[13px] opacity-60">{{ $note }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
