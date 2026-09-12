@php
    use App\Support\Money;

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
        @if ($balance > 0)
            <x-btn wire:click="requestBlik">Poproś o BLIK</x-btn>
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
</div>
