@php
    use App\Support\Money;
@endphp

<div>
    @if ($open && $client)
        <div class="dialog-backdrop" x-on:keydown.escape.window="$wire.close()">
            <div class="dialog w-[min(560px,100%)]" role="dialog" aria-modal="true" aria-labelledby="delete-data-title">
                <div class="flex items-baseline gap-3">
                    <div>
                        <p class="text-[10px] tracking-[0.14em] text-accent uppercase">Żądanie usunięcia danych</p>
                        <p class="dialog-title tracking-[-0.02em]" id="delete-data-title">{{ $client->name }}</p>
                    </div>
                    <x-btn variant="ghost" class="ml-auto text-lg" wire:click="close" aria-label="Zamknij">✕</x-btn>
                </div>

                <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(210px,1fr))]">
                    <div>
                        <p class="mb-1.5 text-[10px] tracking-[0.14em] text-accent uppercase">Znika</p>
                        <ul class="text-[13px] leading-relaxed opacity-80">
                            <li>telefon, e-mail, opiekun</li>
                            <li>cel i punkt startowy</li>
                            <li>kontuzje i przeciwwskazania</li>
                            <li>notatki trenera i „na następny raz"</li>
                            <li>notatki ze wszystkich sesji</li>
                            <li>tagi, pliki i plany z dysku</li>
                            <li>imię i nazwisko → „Dane usunięte #{{ str_pad((string) $client->getKey(), 4, '0', STR_PAD_LEFT) }}"</li>
                        </ul>
                    </div>

                    <div>
                        <p class="mb-1.5 text-[10px] tracking-[0.14em] text-accent uppercase">Zostaje</p>
                        <ul class="text-[13px] leading-relaxed opacity-80">
                            <li>daty i kwoty sesji</li>
                            <li>statusy płatności</li>
                            <li>wpisy w logu zmian</li>
                        </ul>
                        <p class="mt-2 text-xs opacity-60">
                            Kwoty muszą zgadzać się z tym, co poszło do urzędu — dlatego historia zostaje,
                            ale bez notatek.
                        </p>
                    </div>
                </div>

                <p class="border-l-[3px] border-accent bg-accent-100 p-3 text-[13px]">
                    <strong>Tego nie da się cofnąć.</strong> Karta trafia do archiwum, a operacja zapisuje się
                    w logu zmian razem z Twoim nazwiskiem.
                </p>

                @if ($owed > 0)
                    <p class="border-l-[3px] border-accent bg-bg p-3 text-[13px]">
                        Na saldzie zostaje <strong>{{ Money::format($owed) }}</strong>. Po usunięciu danych
                        <strong>nie wyślesz już prośby o płatność</strong> — nie będzie na jaki numer.
                    </p>
                @endif

                <div class="dialog-actions">
                    <x-btn wire:click="close">Anuluj</x-btn>
                    <x-btn variant="primary" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">
                        <span wire:loading.remove wire:target="confirm">Usuń dane</span>
                        <span wire:loading wire:target="confirm">Usuwam…</span>
                    </x-btn>
                </div>
            </div>
        </div>
    @endif
</div>
