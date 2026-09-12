@php
    use App\Support\Money;
    use App\Support\Plural;
    use App\Support\PolishMonth;
@endphp

<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">
        Cześć, {{ $firstName }}.<br>{{ $owed->isNotEmpty() ? 'Do wbicia i do odzyskania.' : 'Wszystko rozliczone.' }}
    </h1>
    <p class="mt-2 max-w-[62ch] text-muted">
        Grafik zostaje w Google Calendar. Tutaj wbijasz fakty: kto przyszedł, za ile i czy zapłacił.
        Stawkę ustalasz sam, a całość idzie do Ciebie.
    </p>

    <hr class="hr">

    <x-stat-bar class="mb-9" :items="[
        [
            'label' => 'Sesje '.PolishMonth::inMonth($month->start()),
            'value' => (string) $summary->completedSessions,
            'hint' => 'tylko odbyte',
        ],
        [
            'label' => 'Zarobek — '.PolishMonth::name($month->start()),
            'value' => Money::format($summary->revenue),
            'hint' => '100% Twoje',
        ],
        [
            'label' => 'Nierozliczone',
            'value' => Money::format($owedTotal),
            'hint' => $owed->isEmpty()
                ? 'nikt nic nie wisi'
                : Plural::of($owed->count(), 'klient', 'klienci', 'klientów'),
        ],
        ['label' => 'Aktywni klienci', 'value' => (string) $clients, 'hint' => 'bez archiwalnych'],
    ]" />

    <div class="grid gap-10 [grid-template-columns:repeat(auto-fit,minmax(320px,1fr))]">
        <section>
            <div class="flex items-baseline gap-2.5 border-b-2 border-divider pb-2.5">
                <h3>Do odzyskania</h3>
                @if ($owed->isNotEmpty())
                    <span class="ml-auto text-xs text-muted">{{ Money::format($owedTotal) }}</span>
                @endif
            </div>

            @forelse ($owed as $row)
                <div class="flex flex-wrap items-center gap-3 border-b border-divider py-3.5"
                     wire:key="owed-{{ $row->client->getKey() }}">
                    <div class="min-w-[150px] flex-1">
                        <a href="{{ route('clients.show', $row->client) }}"
                           class="block font-extrabold hover:text-accent-700">{{ $row->client->name }}</a>
                        <p class="mt-1 text-xs opacity-55">
                            {{ Plural::of($row->sessions, 'sesja', 'sesje', 'sesji') }}
                            · ostatnia {{ $row->latestOn->format('d.m.Y') }}
                        </p>
                    </div>

                    <span class="font-[800] text-[19px] text-accent-700">{{ Money::format($row->amount) }}</span>

                    <x-btn variant="ghost" class="text-xs"
                           wire:click="remind({{ $row->client->getKey() }})"
                           wire:loading.attr="disabled"
                           wire:target="remind({{ $row->client->getKey() }})">Monit SMS</x-btn>
                </div>
            @empty
                <x-empty-state class="mt-5" title="Zero zaległości.">
                    Wszyscy zapłacili. Pierwsza sesja wbita na saldo pojawi się tutaj.
                </x-empty-state>
            @endforelse
        </section>

        <section>
            <div class="flex items-baseline gap-2.5 border-b-2 border-divider pb-2.5">
                <h3>Ostatnio wbite</h3>
                <x-btn variant="ghost" class="ml-auto text-xs" :href="route('sessions.index')">Cała historia →</x-btn>
            </div>

            @forelse ($recent as $session)
                <div class="flex flex-wrap items-baseline gap-2.5 border-b border-divider py-3"
                     wire:key="recent-{{ $session->getKey() }}">
                    <span class="min-w-[74px] text-xs opacity-50">{{ $session->date->format('d.m.Y') }}</span>
                    <span class="font-extrabold">{{ $session->client->name }}</span>
                    <span class="ml-auto text-sm font-extrabold">{{ Money::format($session->price) }}</span>
                    <x-session-status :session="$session" />
                </div>
            @empty
                <x-empty-state class="mt-5" title="Nic jeszcze nie wbite.">
                    Pierwsza sesja pojawi się tutaj, gdy tylko ją zapiszesz.
                </x-empty-state>
            @endforelse
        </section>
    </div>

    @if ($plans->isNotEmpty())
        <section class="mt-10">
            <div class="flex items-baseline gap-2.5 border-b-2 border-divider pb-2.5">
                <h3>Na następny raz</h3>
                <span class="ml-auto text-xs text-muted">tylko dla Ciebie — nie idzie do klienta</span>
            </div>

            <div class="grid gap-x-8 [grid-template-columns:repeat(auto-fit,minmax(280px,1fr))]">
                @foreach ($plans as $plan)
                    <div class="border-b border-divider py-3.5" wire:key="plan-{{ $plan->getKey() }}">
                        <a href="{{ route('clients.show', $plan) }}"
                           class="block text-sm font-extrabold hover:text-accent-700">{{ $plan->name }}</a>
                        <p class="mt-1 text-[13px] opacity-70">{{ $plan->next_session_plan }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
