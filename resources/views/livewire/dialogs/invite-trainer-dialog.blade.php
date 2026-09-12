<div>
    @if ($open)
        <div class="dialog-backdrop" x-on:keydown.escape.window="$wire.close()">
            <div class="dialog w-[min(520px,100%)]" role="dialog" aria-modal="true" aria-labelledby="invite-trainer-title">
                <div class="flex items-baseline gap-3">
                    <div>
                        <p class="text-[10px] tracking-[0.14em] text-accent uppercase">Nowy trener</p>
                        <p class="dialog-title tracking-[-0.02em]" id="invite-trainer-title">Zaproś trenera</p>
                    </div>
                    <x-btn variant="ghost" class="ml-auto text-lg" wire:click="close" aria-label="Zamknij">✕</x-btn>
                </div>

                <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(200px,1fr))]">
                    <x-input name="name" label="Imię i nazwisko *" placeholder="np. Katarzyna Samborska"
                             wire:model="name" :value="$name" autofocus />
                    <x-input name="email" type="email" label="E-mail *" placeholder="imie@samtrening.com"
                             wire:model="email" :value="$email" />
                </div>

                <x-input name="specialty" label="Specjalizacja" placeholder="np. Zdrowa ciąża, trening kobiet"
                         wire:model="specialty" :value="$specialty" />

                <p class="border-l-[3px] border-accent bg-bg p-3 text-[13px]">
                    Link do ustawienia hasła jest ważny <strong>7 dni</strong>. Do czasu aktywacji konto nie
                    zobaczy żadnych danych klientów — ani swoich, ani cudzych.
                </p>

                <div class="dialog-actions">
                    <x-btn wire:click="close">Anuluj</x-btn>
                    <x-btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                        Wyślij zaproszenie
                    </x-btn>
                </div>
            </div>
        </div>
    @endif
</div>
