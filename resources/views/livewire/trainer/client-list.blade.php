@php
    use App\Support\Money;
@endphp

<div>
    <div class="mb-6 flex flex-wrap items-end gap-5">
        <h1 class="min-w-[260px] flex-1 text-[clamp(32px,4.4vw,46px)]">Twoi klienci.<br>Nie baza kontaktów.</h1>
        <x-btn variant="primary" x-on:click="$dispatch('add-client')">＋ Dodaj klienta</x-btn>
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-2.5">
        <input
            type="search"
            class="input max-w-[320px]"
            placeholder="Szukaj — nazwisko, telefon, tag"
            aria-label="Szukaj klienta"
            wire:model.live.debounce.300ms="search"
        >

        <x-seg name="filtr" :options="$filters" :value="$filter" wire:model.live="filter" />

        <span class="ml-auto text-xs text-muted">{{ $rows->count() }} z {{ $total }}</span>
    </div>

    @if ($rows->isNotEmpty())
        <x-data-table :columns="['Klient', 'Charakterystyka', 'Ostatnia sesja', 'Stawka', 'Saldo', '']">
            @foreach ($rows as $row)
                <tr wire:key="client-{{ $row->client->getKey() }}">
                    <td data-label="Klient">
                        <div>
                            <div class="font-extrabold">{{ $row->client->name }}</div>
                            <div class="text-xs opacity-55">{{ $row->client->phone }}</div>
                        </div>
                    </td>
                    <td data-label="Charakter.">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($row->client->tags as $tag)
                                <x-tag :variant="$tag->variant">{{ $tag->label }}</x-tag>
                            @endforeach
                        </div>
                    </td>
                    <td data-label="Ostatnia" class="text-[13px]">
                        {{ $row->lastSessionOn?->format('d.m.Y') ?? '—' }}
                    </td>
                    <td data-label="Stawka" class="text-right text-[13px]">{{ Money::format($row->client->rate) }}</td>
                    <td data-label="Saldo" @class(['text-right font-extrabold', 'text-accent-700' => $row->owes()])>
                        {{ $row->owes() ? Money::format($row->balance) : '—' }}
                    </td>
                    <td data-label="" class="text-right">
                        <x-btn variant="ghost" :href="route('clients.show', $row->client)" class="text-xs">Karta →</x-btn>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    @else
        <x-empty-state title="Brak klientów">
            {{ $emptyMessage }}
            <x-slot:action>
                <x-btn variant="primary" class="text-xs" x-on:click="$dispatch('add-client')">＋ Dodaj klienta</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</div>
