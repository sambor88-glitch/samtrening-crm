<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">Kto co zmienił.</h1>
    <p class="mt-2 max-w-[56ch] text-muted">
        Stawki ustalacie ręcznie, a kwoty sesji da się nadpisać. Bez tej listy sporu „ja tego nie
        zmieniałem" nie da się rozstrzygnąć. Wpisów nie można edytować ani kasować — także Tobie.
    </p>

    <hr class="hr">

    @if ($entries->isNotEmpty())
        <x-data-table :columns="['Kiedy', 'Kto', 'Co się stało', 'Kontekst']">
            @foreach ($entries as $entry)
                <tr wire:key="log-{{ $entry->getKey() }}">
                    <td data-label="Kiedy" class="text-xs font-extrabold whitespace-nowrap">
                        {{ $entry->happened_at->format('d.m.Y · H:i') }}
                    </td>
                    <td data-label="Kto" class="text-[13px] whitespace-nowrap">{{ $entry->actor_name }}</td>
                    <td data-label="Co się stało" class="text-sm font-semibold">{{ $entry->action }}</td>
                    <td data-label="Kontekst" class="text-[13px] opacity-70">{{ $entry->context }}</td>
                </tr>
            @endforeach
        </x-data-table>

        @if ($entries->count() < $total)
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <x-btn wire:click="more" wire:loading.attr="disabled" wire:target="more">Pokaż starsze</x-btn>
                <span class="text-xs text-muted">{{ $entries->count() }} z {{ $total }}</span>
            </div>
        @endif
    @else
        <x-empty-state title="Log jest pusty">
            Nikt jeszcze nic nie zmienił. Pierwszy wpis pojawi się przy pierwszej wbitej sesji.
        </x-empty-state>
    @endif
</div>
