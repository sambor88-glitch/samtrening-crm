<div>
    @if ($reminder)
        <div class="dialog-backdrop">
            <div class="dialog w-[min(440px,100%)]" role="dialog" aria-modal="true" aria-labelledby="reminder-title">
                <p class="text-[10px] tracking-[0.14em] text-accent uppercase">Przypomnienie od właściciela</p>
                <p class="dialog-title" id="reminder-title">{{ $reminder->data['client'] ?? 'Klient' }}</p>

                <div class="dialog-body">
                    <p class="text-sm">
                        Nierozliczone: <strong>{{ $reminder->data['amount'] ?? '—' }}</strong>.
                        Przypomniał: {{ $reminder->data['from'] ?? 'właściciel studia' }}.
                    </p>
                    <p class="mt-2 text-xs text-muted">
                        Rozliczenie prowadzisz Ty — poproś o BLIK z karty klienta albo odznacz wpłatę,
                        jeśli już jest.
                    </p>

                    @if ($waiting > 1)
                        <p class="mt-2 text-xs text-muted">Czeka jeszcze {{ $waiting - 1 }}.</p>
                    @endif
                </div>

                <div class="dialog-actions">
                    <x-btn variant="primary" wire:click="dismiss('{{ $reminder->id }}')"
                            wire:loading.attr="disabled" wire:target="dismiss('{{ $reminder->id }}')">Rozumiem</x-btn>
                </div>
            </div>
        </div>
    @endif
</div>
