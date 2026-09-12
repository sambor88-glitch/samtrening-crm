@php
    use App\Support\Money;
@endphp

<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">Co się odbyło.</h1>
    <p class="mt-5 max-w-[56ch] text-muted">
        Jedno źródło prawdy. Wbicie sesji to jedyny moment, w którym powstaje należność —
        i jedyne, co liczy się do Twojego zarobku.
    </p>

    <hr class="hr">

    @if ($page->rows->isNotEmpty())
        <x-data-table :columns="['Data', 'Klient', 'Usługa', 'Notatka trenera', 'Kwota', 'Status']">
            @foreach ($page->rows as $session)
                <tr wire:key="session-{{ $session->getKey() }}">
                    <td data-label="Data" class="text-xs font-extrabold whitespace-nowrap">
                        {{ $session->date->format('d.m.Y') }}
                    </td>
                    <td data-label="Klient" class="text-sm font-semibold whitespace-nowrap">
                        {{ $session->client->name }}
                    </td>
                    <td data-label="Usługa" class="text-[13px]">{{ $session->service }}</td>
                    <td data-label="Notatka" class="text-xs opacity-65">{{ $session->notes ?: 'Bez notatki.' }}</td>
                    <td data-label="Kwota" class="text-right text-sm font-extrabold">{{ Money::format($session->price) }}</td>
                    <td data-label="Status" class="text-right"><x-session-status :session="$session" /></td>
                </tr>
            @endforeach
        </x-data-table>

        @if ($page->hasMore())
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <x-btn wire:click="more" wire:loading.attr="disabled" wire:target="more">Pokaż starsze</x-btn>
                <span class="text-xs text-muted">{{ $page->rows->count() }} z {{ $page->total }}</span>
            </div>
        @endif
    @else
        <x-empty-state title="Nic jeszcze nie wbite">
            Sesje pojawią się tutaj po pierwszym wbiciu — z karty klienta albo z paska na górze.
            <x-slot:action>
                <x-btn variant="primary" class="text-xs" x-on:click="$dispatch('log-session')">＋ Wbij sesję</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</div>
