@php
    use App\Support\Money;
@endphp

<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">Wszyscy klienci.</h1>

    <div class="mt-6 mb-5 flex flex-wrap items-center gap-2.5">
        <input
            type="search"
            class="input max-w-[320px]"
            placeholder="Szukaj — nazwisko, telefon, tag"
            aria-label="Szukaj klienta w całym studiu"
            wire:model.live.debounce.300ms="search"
        >

        <x-seg name="trener" :options="$trainers" :value="$trainer" wire:model.live="trainer" />

        <span class="ml-auto text-xs text-muted">{{ $rows->count() }} z {{ $total }}</span>
    </div>

    {{-- Szkielet ma tyle wierszy, ile widać teraz — dzięki temu wysokość się nie zmienia. --}}
    <x-skeleton-rows wire:loading.delay wire:target="search,trainer" :rows="max($rows->count(), 3)" class="mt-2" />

    <div wire:loading.delay.remove wire:target="search,trainer">
        @if ($rows->isNotEmpty())
            <x-data-table :columns="['Klient', 'Trener', 'Charakterystyka', 'Ostatnia sesja', 'Stawka', 'Saldo', '']">
                @foreach ($rows as $row)
                    <tr wire:key="studio-client-{{ $row->client->getKey() }}">
                        <td data-label="Klient">
                            <div>
                                <div class="font-extrabold">{{ $row->client->name }}</div>
                                <div class="text-xs opacity-55">{{ $row->client->phone }}</div>
                            </div>
                        </td>
                        <td data-label="Trener" class="text-[13px] whitespace-nowrap">{{ $row->client->trainer->name }}</td>
                        <td data-label="Charakter.">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($row->client->tags as $tag)
                                    <x-tag :variant="$tag->variant">{{ $tag->label }}</x-tag>
                                @endforeach

                                @if ($row->client->archived)
                                    <x-tag variant="outline">Archiwum</x-tag>
                                @endif
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
            <x-empty-state :title="$total === 0 ? 'Kartoteka studia jest pusta' : 'Nikt nie pasuje'">
                {{ $total === 0
                    ? 'Pierwszego klienta zakłada trener u siebie — tutaj pojawi się sam.'
                    : 'Żaden klient nie pasuje do tego filtra. Zmień trenera albo wyczyść wyszukiwanie.' }}
            </x-empty-state>
        @endif
    </div>
</div>
