<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">Co dostaje klient.</h1>
    <p class="mt-2 max-w-[56ch] text-muted">
        Każdy tekst możesz przepisać po swojemu. Podgląd wypełnia się prawdziwymi danymi wybranej osoby,
        więc od razu widać, czy wiadomość brzmi jak Ty.
    </p>

    <div class="mt-6 max-w-[320px]">
        <x-select name="clientId" label="Podgląd dla klienta" :options="$clients" :value="$clientId"
                  placeholder="— wybierz klienta —" wire:model.live="clientId" />
    </div>

    @unless ($hasClient)
        <p class="alert">Podgląd wypełni się danymi, gdy dodasz pierwszego klienta. Szablony możesz pisać już teraz.</p>
    @endunless

    <hr class="hr">

    <div class="grid gap-8 [grid-template-columns:repeat(auto-fit,minmax(340px,1fr))]">
        @foreach ($sms as $card)
            <section class="border-t-2 border-divider pt-[18px]" wire:key="template-{{ $card['key'] }}">
                <x-tag variant="accent">SMS</x-tag>
                <h4 class="mt-2.5">{{ $card['name'] }}</h4>
                <p class="text-xs text-muted">{{ $card['when'] }}</p>

                <p class="mt-3.5 max-w-[320px] border border-divider border-l-[3px] border-l-accent bg-surface p-3 text-[13px] whitespace-pre-wrap">{{ $card['preview'] }}</p>

                <p class="mt-2 text-[11px] tracking-[0.08em] text-muted uppercase">{{ $card['count']->label() }}</p>

                <div class="field mt-3.5">
                    <label for="body-{{ $card['key'] }}">Szablon</label>
                    <textarea id="body-{{ $card['key'] }}" class="input min-h-[92px] text-[13px]"
                              wire:model="bodies.{{ $card['key'] }}">{{ $bodies[$card['key']] ?? '' }}</textarea>
                    @error('bodies.'.$card['key'])
                        <p class="alert">{{ $message }}</p>
                    @enderror
                </div>

                <x-btn class="mt-2 text-xs" wire:click="save('{{ $card['key'] }}')"
                        wire:loading.attr="disabled" wire:target="save('{{ $card['key'] }}')">
                    <span wire:loading.remove wire:target="save('{{ $card['key'] }}')">Zapisz szablon</span>
                    <span wire:loading wire:target="save('{{ $card['key'] }}')">Zapisuję…</span>
                </x-btn>
            </section>
        @endforeach

        <section class="border-t-2 border-divider pt-[18px]" wire:key="template-statement">
            <x-tag variant="outline">E-mail</x-tag>
            <h4 class="mt-2.5">Zbiorcze podsumowanie miesiąca</h4>
            <p class="text-xs text-muted">Ręcznie, przyciskiem z zakładki Płatności. Odpowiedź idzie do Ciebie, nie do studia.</p>

            <div class="mt-3.5 bg-surface">
                <p class="border-b border-divider p-3 text-[13px] font-extrabold">{{ $subject }}</p>
                <p class="p-3 text-[13px] whitespace-pre-wrap">{{ $body }}</p>
            </div>

            <div class="field mt-3.5">
                <label for="body-statement_subject">Temat</label>
                <input id="body-statement_subject" class="input text-[13px]"
                       value="{{ $bodies['statement_subject'] ?? '' }}" wire:model="bodies.statement_subject">
                @error('bodies.statement_subject')
                    <p class="alert">{{ $message }}</p>
                @enderror
            </div>

            <x-btn class="mt-2 text-xs" wire:click="save('statement_subject')"
                    wire:loading.attr="disabled" wire:target="save('statement_subject')">
                <span wire:loading.remove wire:target="save('statement_subject')">Zapisz temat</span>
                <span wire:loading wire:target="save('statement_subject')">Zapisuję…</span>
            </x-btn>

            <div class="field mt-3.5">
                <label for="body-statement_body">Treść</label>
                <textarea id="body-statement_body" class="input min-h-[160px] text-[13px]"
                          wire:model="bodies.statement_body">{{ $bodies['statement_body'] ?? '' }}</textarea>
                @error('bodies.statement_body')
                    <p class="alert">{{ $message }}</p>
                @enderror
            </div>

            <x-btn class="mt-2 text-xs" wire:click="save('statement_body')"
                    wire:loading.attr="disabled" wire:target="save('statement_body')">
                <span wire:loading.remove wire:target="save('statement_body')">Zapisz treść</span>
                <span wire:loading wire:target="save('statement_body')">Zapisuję…</span>
            </x-btn>
        </section>
    </div>
</div>
