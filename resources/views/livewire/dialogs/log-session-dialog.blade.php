<div>
    @if ($open)
        <div class="dialog-backdrop" x-on:keydown.escape.window="$wire.close()">
            <div class="dialog w-[min(560px,100%)]" role="dialog" aria-modal="true" aria-labelledby="log-session-title">
                <div class="flex items-baseline gap-3">
                    <div>
                        <p class="text-[10px] tracking-[0.14em] text-accent uppercase">Zrealizowana usługa</p>
                        <p class="dialog-title tracking-[-0.02em]" id="log-session-title">Wbij sesję</p>
                    </div>
                    <x-btn variant="ghost" class="ml-auto text-lg" wire:click="close" aria-label="Zamknij">✕</x-btn>
                </div>

                <div class="field">
                    <label>Co się stało</label>
                    <x-seg
                        name="kind"
                        :options="['completed' => 'Odbyta', 'cancelled' => 'Odwołana', 'no_show' => 'Nie przyszedł']"
                        :value="$kind"
                        wire:model.live="kind"
                    />
                </div>

                <x-select name="clientId" label="Klient" :options="$clients" :value="$clientId"
                          placeholder="— wybierz klienta —" wire:model.live="clientId" />

                <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(140px,1fr))]">
                    <x-select name="service" label="Usługa" :options="$services" :value="$service"
                              wire:model.live="service" />
                    <x-input name="date" type="date" label="Data" :value="$date" wire:model.live="date" />
                    <x-input name="price" type="number" step="5" min="0" label="Kwota (zł)" :value="$price"
                             :hint="$rateHint" wire:model="price" />
                </div>

                <x-input name="notes" label="Notatka z sesji" multiline
                         placeholder="Co się działo na treningu, co poprawialiście"
                         wire:model="notes">{{ $notes }}</x-input>

                <x-input name="plan" label="Na następny raz" placeholder="Co zrobić na kolejnej sesji"
                         hint="Zapisuje się na karcie klienta." :value="$plan" wire:model="plan" />

                <div class="field">
                    <label>{{ $settlementLabel }}</label>
                    <div class="grid gap-2">
                        @foreach ($settlementOptions as $value => $option)
                            <label class="radio items-start border border-divider bg-bg px-[11px] py-[9px]">
                                <input type="radio" value="{{ $value }}" wire:model.live="settlement" @checked($settlement === $value)>
                                <span class="dot mt-0.5"></span>
                                <span>
                                    <span class="block text-[13px] font-extrabold">{{ $option['label'] }}</span>
                                    <span class="text-xs opacity-60">{{ $option['hint'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @if ($duplicate)
                    <p class="alert">{{ $duplicate }}</p>
                @endif

                <div class="dialog-actions">
                    <x-btn wire:click="close">Anuluj</x-btn>
                    <x-btn variant="primary" id="log-session-save" wire:click="save"
                           wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $saveLabel }}</span>
                        <span wire:loading wire:target="save">Zapisuję…</span>
                    </x-btn>
                </div>
            </div>
        </div>
    @endif
</div>
