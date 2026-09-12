<div>
    @if ($open)
        <div class="dialog-backdrop" x-on:keydown.escape.window="$wire.close()">
            <div class="dialog w-[min(560px,100%)]" role="dialog" aria-modal="true" aria-labelledby="client-dialog-title">
                <div class="flex items-baseline gap-3">
                    <div>
                        <p class="text-[10px] tracking-[0.14em] text-accent uppercase">{{ $kicker }}</p>
                        <p class="dialog-title tracking-[-0.02em]" id="client-dialog-title">{{ $title }}</p>
                    </div>
                    <x-btn variant="ghost" class="ml-auto text-lg" wire:click="close" aria-label="Zamknij">✕</x-btn>
                </div>

                <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(200px,1fr))]">
                    <x-input name="name" label="Imię i nazwisko *" placeholder="np. Anna Kowalska"
                             wire:model.live.debounce.400ms="name" :value="$name" autofocus />
                    <x-input name="phone" type="tel" label="Telefon" placeholder="+48 600 000 000"
                             wire:model="phone" :value="$phone" />
                    <x-input name="email" type="email" label="E-mail" placeholder="anna@example.com"
                             wire:model="email" :value="$email" />
                    <x-input name="rate" type="number" step="5" min="0" label="Stawka za sesję (zł)"
                             wire:model="rate" :value="$rate" />
                </div>

                <x-input name="goal" label="Cel i punkt startowy"
                         placeholder="np. redukcja, powrót do formy po przerwie"
                         wire:model="goal" :value="$goal" />

                <x-input name="contraindications" label="Kontuzje i przeciwwskazania" multiline
                         placeholder="Co pomijamy, na co uważamy. Zostaw puste, jeśli brak."
                         :hint="$consent ? null : 'Zgody RODO jeszcze nie ma — odbierz ją przed pierwszą sesją.'"
                         wire:model="contraindications">{{ $contraindications }}</x-input>

                <x-input name="guardian" label="Opiekun — wymagany dla osób poniżej 18 lat"
                         placeholder="Imię, nazwisko i telefon rodzica"
                         wire:model="guardian" :value="$guardian" />

                <x-input name="trainerNotes" label="Notatka trenera" multiline
                         placeholder="Jak z nim pracować, na co zwracać uwagę, czego nie robić"
                         wire:model="trainerNotes">{{ $trainerNotes }}</x-input>

                <x-check label="Faktura na firmę" hint="Zamiast paragonu. Dane trafiają na kartę klienta."
                         wire:model.live="invoice" :checked="$invoice" />

                @if ($invoice)
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-input name="companyName" label="Nazwa firmy" wire:model="companyName" :value="$companyName" />
                        <x-input name="taxId" label="NIP" placeholder="0000000000" wire:model="taxId" :value="$taxId" />
                    </div>
                @endif

                <x-check label="Zgoda RODO odebrana"
                         hint="Kontakt i dane o zdrowiu. Odbierz ją przed pierwszą sesją."
                         wire:model.live="consent" :checked="$consent" />

                <div class="dialog-actions">
                    <x-btn wire:click="close">Anuluj</x-btn>
                    <x-btn variant="primary" id="client-dialog-save" wire:click="save"
                           :disabled="trim($name) === ''">{{ $saveLabel }}</x-btn>
                </div>
            </div>
        </div>
    @endif
</div>
