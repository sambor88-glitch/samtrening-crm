@php
    use App\Support\Money;
    use App\Support\Plural;
    use Illuminate\Support\Number;

    // A card imported or fixed by hand can carry consent without a date — do not print a dangling separator.
    $consentStatus = match (true) {
        ! $client->consent_given => 'BRAK ZGODY — uzupełnij przed pierwszą sesją',
        $client->consent_date !== null => 'Zgoda odebrana · '.$client->consent_date->format('d.m.Y'),
        default => 'Zgoda odebrana',
    };
@endphp

<div>
    <x-btn variant="ghost" :href="route('clients.index')" class="mb-4 text-xs">← Klienci</x-btn>

    <div class="mb-5 flex flex-wrap items-start gap-5">
        <div class="min-w-[260px] flex-1">
            <h1 class="text-[clamp(30px,4.2vw,44px)]">{{ $client->name }}</h1>

            @if ($client->tags->isNotEmpty())
                <div class="mt-2.5 flex flex-wrap gap-1">
                    @foreach ($client->tags as $tag)
                        <x-tag :variant="$tag->variant">{{ $tag->label }}</x-tag>
                    @endforeach
                </div>
            @endif
        </div>

        @unless ($prepayment->isEmpty())
            <div>
                <p class="mb-1.5 text-[10px] tracking-[0.14em] uppercase opacity-55">Zostało z przedpłaty</p>
                <p class="text-[40px] leading-none font-extrabold tracking-[-0.03em]">
                    {{ Money::format($prepayment->left) }}
                </p>
            </div>
        @endunless

        <div>
            <p class="mb-1.5 text-[10px] tracking-[0.14em] uppercase opacity-55">Saldo</p>
            <p @class(['text-[40px] leading-none font-extrabold tracking-[-0.03em]', 'text-accent' => $balance > 0])>
                {{ Money::format($balance) }}
            </p>
        </div>
    </div>

    <div class="mb-5 flex flex-wrap gap-2">
        <x-btn variant="primary" x-on:click="$dispatch('log-session', { client: {{ $client->getKey() }} })">＋ Wbij sesję</x-btn>
        <x-btn x-on:click="$dispatch('edit-client', { client: {{ $client->getKey() }} })">Edytuj kartę</x-btn>
        <x-btn href="#przedplata">Wpłata z góry ↓</x-btn>
        @if ($balance > 0)
            <x-btn wire:click="requestBlik" wire:loading.attr="disabled" wire:target="requestBlik">
                <span wire:loading.remove wire:target="requestBlik">Poproś o BLIK</span>
                <span wire:loading wire:target="requestBlik">Wysyłam…</span>
            </x-btn>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-4 border-b-2 border-divider bg-surface p-[18px]">
        <div class="min-w-[200px] flex-1">
            <p class="mb-0.5 text-[10px] tracking-[0.14em] text-accent uppercase">Stawka tego klienta</p>
            <p class="text-xs text-muted">Ustalasz ją sam. Podpowiada się przy wbijaniu sesji, każdą kwotę możesz nadpisać.</p>
        </div>

        <div class="flex items-center gap-2">
            <input
                type="number"
                step="5"
                min="0"
                class="input w-[110px] text-[17px] font-extrabold"
                aria-label="Stawka za sesję w złotych"
                value="{{ $rate }}"
                wire:model="rate"
                wire:change="saveRate"
            >
            <span class="text-[17px] font-extrabold">zł / sesja</span>
        </div>
    </div>

    @error('rate')
        <p class="alert">{{ $message }}</p>
    @enderror

    {{-- Pasek RODO. „Usuń dane na żądanie" dochodzi w SC-46. --}}
    <div class="flex flex-wrap items-center gap-3 border-b-2 border-divider py-3">
        <x-btn variant="ghost" class="text-xs" wire:click="exportData"
               wire:loading.attr="disabled" wire:target="exportData">Eksport danych klienta</x-btn>

        <x-btn variant="ghost" class="text-xs" wire:click="toggleArchive"
               wire:loading.attr="disabled" wire:target="toggleArchive"
               :disabled="$balance > 0 && ! $client->archived">
            {{ $client->archived ? 'Przywróć z archiwum' : 'Archiwizuj klienta' }}
        </x-btn>

        @if ($balance > 0 && ! $client->archived)
            <span class="text-xs text-accent-700">Najpierw rozlicz saldo</span>
        @endif

        @if ($client->archived)
            <x-tag variant="outline">Archiwum</x-tag>
        @endif

        <x-btn variant="ghost" class="ml-auto text-xs text-accent-700"
               x-on:click="$dispatch('delete-client-data', { client: {{ $client->getKey() }} })">
            Usuń dane na żądanie
        </x-btn>
    </div>

    <div class="grid [grid-template-columns:repeat(auto-fit,minmax(260px,1fr))]">
        <x-info-block label="Kontakt">
            @foreach ([
                'Telefon' => $client->phone,
                'E-mail' => $client->email,
                'Trener' => $client->trainer->name,
                'Opiekun' => $client->guardian,
            ] as $label => $value)
                <p class="flex gap-3 py-0.5 text-sm">
                    <span class="w-[70px] flex-none opacity-55">{{ $label }}</span>
                    <span>{{ filled($value) ? $value : '—' }}</span>
                </p>
            @endforeach
        </x-info-block>

        <x-info-block label="Cel i punkt startowy">
            <p class="flex gap-3 py-0.5 text-sm">
                <span class="w-[70px] flex-none opacity-55">Start</span>
                <span>{{ $client->baseline ?: 'Karta założona '.$client->created_at->format('d.m.Y') }}</span>
            </p>
            <p class="mt-2 text-sm">{{ $client->goal ?: 'Do ustalenia na pierwszej sesji.' }}</p>
        </x-info-block>

        <x-info-block label="Kontuzje i przeciwwskazania">
            <p class="text-sm">{{ $client->contraindications ?: 'Brak zgłoszonych.' }}</p>
        </x-info-block>

        <x-info-block label="Zgody RODO">
            <p class="flex gap-3 py-0.5 text-sm">
                <span class="w-[70px] flex-none opacity-55">Status</span>
                <span @class(['text-accent-700 font-extrabold' => ! $client->consent_given])>{{ $consentStatus }}</span>
            </p>
            <p class="mt-2 text-sm">{{ $client->trainer_notes ?: 'Karta założona ręcznie przez trenera.' }}</p>
        </x-info-block>

        <x-info-block label="Na następny raz">
            <p class="text-sm">{{ $client->next_session_plan ?: 'Nic zaplanowanego — dopisz przy wbijaniu sesji.' }}</p>
        </x-info-block>
    </div>

    <section class="mt-10 scroll-mt-20" id="przedplata">
        <div class="flex items-baseline gap-2.5 border-b-2 border-divider pb-2.5">
            <h3>Przedpłata</h3>
            <span class="ml-auto text-xs text-muted">Sesje na saldo schodzą z puli od najstarszej</span>
        </div>

        @if ($prepayment->isEmpty())
            <p class="mt-4 text-[13px] text-muted">
                Klient zapłacił z góry? Zapisz wpłatę — kolejne sesje zejdą z tej puli, a te poza nią trafią na saldo.
            </p>
        @else
            <x-stat-bar class="mt-4" :items="[
                ['label' => 'Wpłacone z góry', 'value' => Money::format($prepayment->paidIn), 'hint' => Plural::of($prepayments->count(), 'wpłata', 'wpłaty', 'wpłat')],
                ['label' => 'Zeszło na sesje', 'value' => Money::format($prepayment->used), 'hint' => Plural::of($prepaidSessions, 'sesja', 'sesje', 'sesji').' w całości z puli'],
                ['label' => 'Zostało', 'value' => Money::format($prepayment->left), 'hint' => $poolHint],
            ]" />

            @foreach ($prepayments as $entry)
                <div class="flex flex-wrap items-center gap-3 border-b border-divider py-[13px]" wire:key="prepayment-{{ $entry->getKey() }}">
                    <span class="min-w-[76px] text-xs font-extrabold opacity-55">{{ $entry->paid_on->format('d.m.Y') }}</span>
                    <p class="min-w-[150px] flex-1 text-sm font-semibold">Wpłata z góry</p>
                    <span class="text-sm font-extrabold">{{ Money::format($entry->amount) }}</span>

                    <x-btn variant="ghost" class="text-xs" wire:click="deletePrepayment({{ $entry->getKey() }})"
                           wire:loading.attr="disabled" wire:target="deletePrepayment({{ $entry->getKey() }})"
                           title="Usuń wpłatę z karty" aria-label="Usuń wpłatę z {{ $entry->paid_on->format('d.m.Y') }}">✕</x-btn>
                </div>
            @endforeach
        @endif

        <div class="mt-5 flex flex-wrap items-end gap-3 bg-surface p-[18px]">
            <div class="min-w-[200px] flex-1 self-center">
                <p class="mb-0.5 text-[10px] tracking-[0.14em] text-accent uppercase">Nowa wpłata z góry</p>
                <p class="text-xs text-muted">Gotówka, przelew albo BLIK — wpisz, ile wpłynęło. Najpierw spłaci saldo, reszta zostanie w puli.</p>
            </div>

            <div class="w-[130px]">
                <x-input name="prepaymentAmount" type="number" step="0.01" min="0" label="Kwota (zł)"
                         :value="$prepaymentAmount" wire:model="prepaymentAmount" />
            </div>

            <div class="w-[160px]">
                <x-input name="prepaymentDate" type="date" label="Data wpłaty" :max="now()->toDateString()"
                         :value="$prepaymentDate" wire:model="prepaymentDate" />
            </div>

            <x-btn variant="primary" wire:click="recordPrepayment" wire:loading.attr="disabled" wire:target="recordPrepayment">
                <span wire:loading.remove wire:target="recordPrepayment">Zapisz wpłatę</span>
                <span wire:loading wire:target="recordPrepayment">Zapisuję…</span>
            </x-btn>
        </div>
    </section>

    <section class="mt-10">
        <div class="flex items-baseline gap-2.5 border-b-2 border-divider pb-2.5">
            <h3>Historia treningów</h3>
            @if ($sessions->isNotEmpty())
                <span class="ml-auto text-xs text-muted">Kwotę każdej sesji możesz nadpisać</span>
            @endif
        </div>

        @forelse ($sessions as $session)
            <div class="flex flex-wrap items-center gap-3 border-b border-divider py-[13px]" wire:key="session-{{ $session->getKey() }}">
                <span class="min-w-[44px] text-xs font-extrabold opacity-55">{{ $session->date->format('d.m') }}</span>

                <div class="min-w-[150px] flex-1">
                    <p class="text-sm font-semibold">{{ $session->service }}</p>
                    <p class="text-xs opacity-60">{{ $session->notes ?: 'Bez notatki.' }}</p>

                    @if (in_array($session->getKey(), $beyondPool, true))
                        <p class="mt-0.5 text-xs font-extrabold text-accent-700">
                            @if ($session->prepaid_amount > 0)
                                Poza przedpłatą: {{ Money::format($session->beyondPrepayment()) }} · {{ Money::format($session->prepaid_amount) }} zeszło z puli
                            @else
                                Poza przedpłatą
                            @endif
                        </p>
                    @endif
                </div>

                <div class="flex items-center gap-1">
                    <input
                        type="number"
                        step="5"
                        min="0"
                        class="input w-[84px] text-right text-sm font-extrabold"
                        aria-label="Kwota sesji z {{ $session->date->format('d.m.Y') }} w złotych"
                        value="{{ $prices[$session->getKey()] ?? '' }}"
                        wire:model="prices.{{ $session->getKey() }}"
                        wire:change="saveSessionPrice({{ $session->getKey() }})"
                    >
                    <span class="text-xs opacity-50">zł</span>
                </div>

                <x-session-status :session="$session" class="min-w-[92px]" />

                <x-btn variant="ghost" class="text-xs" wire:click="deleteSession({{ $session->getKey() }})"
                       wire:loading.attr="disabled" wire:target="deleteSession({{ $session->getKey() }})"
                       title="Usuń sesję z karty" aria-label="Usuń sesję z {{ $session->date->format('d.m.Y') }}">✕</x-btn>

                @error('prices.'.$session->getKey())
                    <p class="alert w-full">{{ $message }}</p>
                @enderror
            </div>

            @if ($session->getKey() === $poolEndsAfter && ! $loop->last)
                <p class="border-b-2 border-accent py-2 text-[10px] font-extrabold tracking-[0.14em] text-accent uppercase" wire:key="pool-end">
                    Tu skończyła się przedpłata — wyżej sesje poza pulą
                </p>
            @endif
        @empty
            <x-empty-state title="Jeszcze żadnej wbitej sesji">
                Historia zapełni się sama — wbijaj po każdym treningu. Kwota podpowie się ze stawki klienta, możesz ją nadpisać.
                <x-slot:action>
                    <x-btn variant="primary" class="text-xs" x-on:click="$dispatch('log-session', { client: {{ $client->getKey() }} })">＋ Wbij pierwszą sesję</x-btn>
                </x-slot:action>
            </x-empty-state>
        @endforelse
    </section>

    <section class="mt-10">
        <div class="flex items-baseline gap-2.5 border-b-2 border-divider pb-2.5">
            <h3>Pliki i plany</h3>
            <span class="ml-auto text-xs text-muted">Link wygasa po 14 dniach</span>
        </div>

        @forelse ($files as $file)
            <div class="flex flex-wrap items-center gap-3 border-b border-divider py-[13px]" wire:key="file-{{ $file->getKey() }}">
                <span class="flex h-[34px] w-[34px] flex-none items-center justify-center bg-surface text-[10px] font-extrabold">
                    {{ $file->extension }}
                </span>

                <div class="min-w-[150px] flex-1">
                    <p class="text-sm font-semibold">{{ $file->name }}</p>
                    <p class="text-xs opacity-60">
                        Wgrany {{ $file->created_at->format('d.m.Y') }} · {{ Number::fileSize($file->size, precision: 1) }}
                    </p>
                </div>

                <x-btn variant="ghost" class="text-xs" wire:click="sendFile({{ $file->getKey() }})"
                        wire:loading.attr="disabled" wire:target="sendFile({{ $file->getKey() }})">Wyślij →</x-btn>
            </div>
        @empty
            <p class="mt-4 text-[13px] text-muted">Jeszcze nic tu nie ma. Wgraj plan, a wyślesz go klientowi SMS-em.</p>
        @endforelse

        <div class="mt-5 border-2 border-dashed border-divider p-[18px]">
            <div class="field">
                <label for="upload">Wgraj plan albo dokument</label>
                <input id="upload" type="file" class="input" wire:model="upload"
                       accept=".pdf,.jpg,.jpeg,.png,.webp,.heic">
                <p class="field-hint">PDF albo zdjęcie, do 8 MB. Plik leży na prywatnym dysku — klient dostaje tylko link.</p>
                @error('upload')
                    <p class="alert">{{ $message }}</p>
                @enderror
            </div>

            <x-btn variant="primary" class="mt-2 text-xs" wire:click="uploadFile"
                   wire:loading.attr="disabled" wire:target="upload,uploadFile">
                <span wire:loading.remove wire:target="upload,uploadFile">Wgraj plik</span>
                <span wire:loading wire:target="upload,uploadFile">Wgrywam…</span>
            </x-btn>
        </div>
    </section>
</div>
