@php
    use App\Support\Money;
    use App\Support\Plural;
@endphp

<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">Kto zapłacił.<br>Kto jeszcze nie.</h1>

    <hr class="hr">

    <x-stat-bar class="mb-9" :items="[
        ['label' => 'Nierozliczone łącznie', 'value' => Money::format($total), 'hint' => 'we wszystkich saldach'],
        ['label' => 'Opłacone '.$inMonth, 'value' => Money::format($paidThisMonth), 'hint' => 'gotówka, przelew, BLIK'],
        ['label' => 'Prośby bez wpłaty', 'value' => (string) $requests, 'hint' => 'SMS poszedł, kasa nie'],
    ]" />

    @if ($rows->isNotEmpty())
        @if ($statements > 0)
        <div class="mb-8 flex flex-wrap items-center gap-3 border-t-2 border-accent bg-surface p-5">
            <div class="min-w-[220px] flex-1">
                <p class="text-lg font-extrabold">Zbiorcze podsumowanie — {{ $monthName }}</p>
                <p class="text-[13px] text-muted">
                    Jeden e-mail na klienta: sesje z tego miesiąca, kwota za ten miesiąc i Twój numer BLIK.
                    Podpisany Tobą, nie studiem.
                </p>
            </div>
            <x-btn variant="primary" wire:click="sendStatements"
                   wire:loading.attr="disabled" wire:target="sendStatements">
                <span wire:loading.remove wire:target="sendStatements">Wyślij {{ $statementsLabel }}</span>
                <span wire:loading wire:target="sendStatements">Wysyłam…</span>
            </x-btn>
        </div>
        @endif

        <x-data-table :columns="['Klient', 'Nierozliczone sesje', 'Najstarsza', 'Kwota', 'Status', '']">
            @foreach ($rows as $row)
                <tr wire:key="owed-{{ $row->client->getKey() }}">
                    <td data-label="Klient" class="font-extrabold whitespace-nowrap">{{ $row->client->name }}</td>
                    <td data-label="Sesje" class="text-[13px]">
                        {{ Plural::of($row->sessions, 'sesja', 'sesje', 'sesji') }}
                    </td>
                    <td data-label="Najstarsza" class="text-[13px]">
                        <div>
                            {{ $row->oldestOn->format('d.m.Y') }}
                            @if ($row->isOverdue($threshold))
                                <span class="block text-xs font-extrabold text-accent-700">
                                    po terminie · {{ Plural::of($row->days, 'dzień', 'dni', 'dni') }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td data-label="Kwota" class="text-right font-extrabold">{{ Money::format($row->amount) }}</td>
                    <td data-label="Status" class="text-right">
                        <x-tag :variant="$row->requested ? 'outline' : 'accent'">
                            {{ $row->requested ? 'Poproszono' : 'Na saldzie' }}
                        </x-tag>
                    </td>
                    <td data-label="" class="text-right">
                        <x-btn variant="ghost" class="text-xs" wire:click="requestBlik({{ $row->client->getKey() }})"
                               wire:loading.attr="disabled" wire:target="requestBlik({{ $row->client->getKey() }})">Poproś o BLIK</x-btn>
                        <x-btn variant="ghost" class="text-xs" wire:click="markPaid({{ $row->client->getKey() }})"
                               wire:loading.attr="disabled" wire:target="markPaid({{ $row->client->getKey() }})">Zapłacone</x-btn>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    @else
        <x-empty-state title="Nic nierozliczonego.">
            Wszystkie sesje opłacone. Kolejna wbita na saldo pojawi się tutaj.
        </x-empty-state>
    @endif
</div>
