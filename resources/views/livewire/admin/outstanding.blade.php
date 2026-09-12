@php
    use App\Support\Money;
    use App\Support\Plural;
@endphp

<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">Co wisi nieopłacone.</h1>

    <div class="mt-6 mb-9 bg-accent p-7 text-bg">
        <p class="text-[clamp(40px,6.5vw,64px)] leading-none font-extrabold tracking-[-0.035em]">
            {{ Money::format($total) }}
        </p>
        <p class="mt-2 text-[13px] opacity-90">
            Rozliczenie prowadzi trener — Ty tylko pilnujesz, żeby nic nie wisiało w nieskończoność.
        </p>
    </div>

    @if ($rows->isNotEmpty())
        <x-data-table :columns="['Klient', 'Trener', 'Nierozliczone', 'Najstarsza', 'Kwota', 'Status', '']">
            @foreach ($rows as $row)
                <tr wire:key="owed-studio-{{ $row->client->getKey() }}">
                    <td data-label="Klient" class="font-extrabold whitespace-nowrap">{{ $row->client->name }}</td>
                    <td data-label="Trener" class="text-[13px] whitespace-nowrap">{{ $row->client->trainer->name }}</td>
                    <td data-label="Nierozliczone" class="text-[13px]">
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
                        <x-btn variant="ghost" class="text-xs"
                               wire:click="nudge({{ $row->client->getKey() }})"
                               wire:loading.attr="disabled" wire:target="nudge({{ $row->client->getKey() }})">Przypomnij trenerowi</x-btn>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    @else
        <x-empty-state title="Nic nie wisi">
            Wszystko rozliczone. Gdy komuś urośnie saldo, pojawi się tutaj razem z trenerem, który je prowadzi.
        </x-empty-state>
    @endif
</div>
