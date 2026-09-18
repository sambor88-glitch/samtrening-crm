@php
    use App\Support\Money;
@endphp

{{-- "Z kalendarza" — SC-65. Proposals, never decisions: the diary does not know who
     called off half an hour before, so nothing here logs itself. --}}
<div class="mb-10">
    @if (! $configured)
        {{-- Nothing connected yet. Say so plainly instead of showing an empty list
             that reads as "nothing to log". --}}
        <x-empty-state title="Kalendarz niepodłączony">
            Podłącz kalendarz Google, a CRM podpowie sesje do wbicia zamiast przepisywania ich z grafiku.
            Zajmuje to jedną zgodę w przeglądarce — instrukcja w <code>docs/AGENT-API.md</code>.
        </x-empty-state>
    @elseif ($failure)
        <x-empty-state title="Kalendarz nie odpowiedział">
            {{ $failure }}
        </x-empty-state>
    @elseif ($matched->isEmpty() && $unmatched->isEmpty())
        <x-empty-state title="Nic do wbicia">
            Wszystko z kalendarza jest już w CRM.
            <x-slot:action>
                <x-btn wire:click="refreshFromCalendar" wire:loading.attr="disabled" wire:target="refreshFromCalendar">
                    Sprawdź ponownie
                </x-btn>
            </x-slot:action>
        </x-empty-state>
    @else
        <h2 class="text-[clamp(22px,2.6vw,30px)]">Z kalendarza.</h2>
        <p class="mt-3 max-w-[56ch] text-muted">
            Te treningi są w grafiku, a nie ma ich w CRM. Odznacz to, co się nie odbyło —
            kalendarz nie wie o odwołaniu w ostatniej chwili.
        </p>

        <x-skeleton-rows wire:loading.delay wire:target="log,refreshFromCalendar" :rows="max($matched->count(), 3)" class="mt-5" />

        <div wire:loading.delay.remove wire:target="log,refreshFromCalendar" class="mt-5">
            @if ($matched->isNotEmpty())
                <x-data-table :columns="['', 'Data', 'Klient', 'W kalendarzu', 'Kwota']">
                    @foreach ($matched as $candidate)
                        <tr wire:key="kandydat-{{ $candidate->key() }}">
                            <td data-label="Wbić">
                                <x-check
                                    wire:model.live="selected"
                                    value="{{ $candidate->key() }}"
                                    :label="''"
                                    aria-label="Wbij sesję z {{ $candidate->startsAt()->format('d.m.Y') }}"
                                />
                            </td>
                            <td data-label="Data" class="text-xs font-extrabold whitespace-nowrap">
                                {{ $candidate->startsAt()->format('d.m.Y') }}
                                <span class="ml-1 opacity-60">{{ $candidate->startsAt()->format('H:i') }}</span>
                            </td>
                            <td data-label="Klient" class="text-sm font-semibold whitespace-nowrap">
                                {{ $candidate->client->name }}
                            </td>
                            <td data-label="W kalendarzu" class="text-xs opacity-65">{{ $candidate->event->title }}</td>
                            <td data-label="Kwota" class="text-right">
                                {{-- The rate on the card is a starting point; a shorter session
                                     or a one-off price is normal, so this stays editable. --}}
                                <input
                                    type="number"
                                    step="5"
                                    min="0"
                                    inputmode="decimal"
                                    class="input w-24 text-right"
                                    wire:model="prices.{{ $candidate->key() }}"
                                    aria-label="Kwota za sesję {{ $candidate->client->name }}, {{ $candidate->startsAt()->format('d.m.Y') }}"
                                >
                                <span class="ml-1 text-xs text-muted">zł</span>
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <x-btn wire:click="log" wire:loading.attr="disabled" wire:target="log" variant="primary">
                        <span wire:loading.remove wire:target="log">Wbij zaznaczone</span>
                        <span wire:loading wire:target="log">Wbijam…</span>
                    </x-btn>
                    <x-btn wire:click="refreshFromCalendar" wire:loading.attr="disabled" wire:target="refreshFromCalendar">
                        Odśwież z kalendarza
                    </x-btn>
                    <span class="text-xs text-muted">
                        Zaznaczono {{ count($selected) }} z {{ $matched->count() }}
                    </span>
                </div>
            @endif

            @if ($unmatched->isNotEmpty())
                {{-- Deliberately not guessed: a title that fits two cards, or none at all.
                     Charging the wrong client is the mistake nobody notices (SC-64). --}}
                <div class="mt-8">
                    <h3 class="text-sm font-extrabold">Nierozpoznane</h3>
                    <p class="mt-2 max-w-[56ch] text-xs text-muted">
                        Tych wpisów nie umiem przypisać do karty. Wbij je ręcznie albo dopisz klientowi
                        alias kalendarzowy — wtedy następnym razem trafią same.
                    </p>

                    <ul class="mt-3 space-y-2">
                        @foreach ($unmatched as $candidate)
                            <li wire:key="nierozpoznany-{{ $candidate->key() }}"
                                class="flex flex-wrap items-baseline gap-x-3 border-l border-divider py-1.5 pl-3">
                                <span class="text-xs font-extrabold whitespace-nowrap">
                                    {{ $candidate->startsAt()->format('d.m.Y H:i') }}
                                </span>
                                <span class="text-sm">{{ $candidate->event->title }}</span>
                                @if ($candidate->isAmbiguous())
                                    <span class="text-xs text-muted">
                                        pasuje do:
                                        {{ collect($candidate->ambiguous)->map(fn ($c) => $c->name)->join(', ') }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <hr class="hr">
    @endif
</div>
