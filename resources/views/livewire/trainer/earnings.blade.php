@php
    use App\Support\Money;
    use App\Support\Plural;
@endphp

<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">Ile zarobiłeś.</h1>
    <p class="mt-2 max-w-[56ch] text-muted">
        Cała kwota, którą płaci klient, jest Twoja. Żadnych prowizji, żadnego rozliczenia ze studiem —
        tylko suma tego, co się odbyło.
    </p>

    <div class="mt-6 flex flex-wrap items-center gap-2">
        @foreach ($ranges as $prefix => $rangeLabel)
            <x-btn :variant="$prefix === $range ? 'primary' : 'secondary'" class="text-xs"
                   wire:click="show('{{ $prefix }}')">{{ $rangeLabel }}</x-btn>
        @endforeach

        <x-btn variant="ghost" class="ml-auto text-xs" wire:click="exportCsv">↓ Eksport CSV</x-btn>
    </div>

    <hr class="hr">

    <div class="mb-9 flex flex-wrap items-center gap-4 bg-accent p-7 text-bg">
        <div class="min-w-[220px] flex-1">
            <p class="text-[11px] tracking-[0.14em] uppercase opacity-85">Zarobek — {{ $label }}</p>
            <p class="text-[clamp(44px,7vw,72px)] leading-[1.02] font-extrabold tracking-[-0.035em]">
                {{ Money::format($summary->revenue) }}
            </p>
            <p class="text-[13px] opacity-90">
                {{ Plural::of($summary->completedSessions, 'odbyta sesja', 'odbyte sesje', 'odbytych sesji') }}
                · 100% stawki klienta
            </p>
        </div>
    </div>

    <x-stat-bar class="mb-9" :items="[
        ['label' => 'Odbyte sesje', 'value' => (string) $summary->completedSessions, 'hint' => $label],
        ['label' => 'Już na koncie', 'value' => Money::format($summary->paid), 'hint' => 'opłacone przez klientów'],
        ['label' => 'Jeszcze do zapłaty', 'value' => Money::format($summary->owed), 'hint' => $isYear ? 'z tego roku na saldach' : 'z tego miesiąca na saldach'],
        ['label' => 'Odwołania i no-show', 'value' => (string) $summary->missedSessions, 'hint' => Money::format($summary->missedRevenue).' naliczone'],
    ]" />

    @if ($sessions->isNotEmpty())
        <x-data-table :columns="['Data', 'Klient', 'Usługa', 'Kwota', 'Status']">
            @foreach ($sessions as $session)
                <tr wire:key="earned-{{ $session->getKey() }}">
                    <td data-label="Data" class="text-xs font-extrabold whitespace-nowrap">{{ $session->date->format('d.m.Y') }}</td>
                    <td data-label="Klient" class="text-sm font-semibold whitespace-nowrap">{{ $session->client->name }}</td>
                    <td data-label="Usługa" class="text-[13px]">{{ $session->service }}</td>
                    <td data-label="Kwota" class="text-right text-sm font-extrabold">{{ Money::format($session->price) }}</td>
                    <td data-label="Status" class="text-right"><x-session-status :session="$session" /></td>
                </tr>
            @endforeach
        </x-data-table>
    @else
        <x-empty-state title="Pusto w tym zakresie">
            {{ $isYear ? 'Żadnej wbitej sesji w tym roku.' : 'Żadnej wbitej sesji w tym miesiącu.' }}
            Wbij pierwszą — kwota policzy się sama.
            <x-slot:action>
                <x-btn variant="primary" class="text-xs" x-on:click="$dispatch('log-session')">＋ Wbij sesję</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</div>
