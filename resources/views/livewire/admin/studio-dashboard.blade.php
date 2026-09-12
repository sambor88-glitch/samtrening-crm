@php
    use App\Support\Money;
@endphp

<div>
    <h1 class="text-[clamp(32px,4.4vw,46px)]">Studio w liczbach.</h1>
    <p class="mt-2 max-w-[56ch] text-muted">
        Trenerzy rozliczają się bezpośrednio z klientami. Ty pilnujesz kont, kartoteki i tego,
        żeby nic nie wisiało nieopłacone.
    </p>

    <div class="mt-6 flex flex-wrap items-center gap-2">
        @foreach ($ranges as $prefix => $label)
            <x-btn :variant="$prefix === $current->prefix() ? 'primary' : 'secondary'" class="text-xs"
                   wire:click="show('{{ $prefix }}')">{{ $label }}</x-btn>
        @endforeach

        <x-btn variant="ghost" class="ml-auto text-xs" wire:click="exportCsv">↓ Eksport CSV — całe studio</x-btn>
    </div>

    <hr class="hr">

    <x-stat-bar class="mb-9" :items="[
        ['label' => 'Sesje — '.$current->label(), 'value' => (string) $studio->completedSessions, 'hint' => 'tylko odbyte'],
        ['label' => 'Klienci studia', 'value' => (string) $clients, 'hint' => 'bez archiwalnych'],
        ['label' => 'Nieopłacone', 'value' => Money::format($outstanding), 'hint' => 'narastająco, poza zakresem'],
        ['label' => 'Trenerzy', 'value' => (string) $trainers, 'hint' => 'aktywne konta'],
    ]" />

    <div class="grid gap-10 [grid-template-columns:repeat(auto-fit,minmax(320px,1fr))]">
        <section>
            <div class="flex items-baseline gap-2.5 border-b-2 border-divider pb-2.5">
                <h3>Trenerzy — {{ $current->label() }}</h3>
                <span class="ml-auto text-xs text-muted">{{ Money::format($studio->revenue) }} obrotu</span>
            </div>

            @foreach ($team as $row)
                <div class="border-b border-divider py-3.5" wire:key="studio-trainer-{{ $row->trainer->getKey() }}">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="font-extrabold">{{ $row->trainer->name }}</span>
                        <span class="text-xs opacity-55">{{ $row->trainer->specialty ?: 'bez specjalizacji' }}</span>
                        <span class="ml-auto text-sm font-extrabold">{{ $row->sessions }}</span>
                    </div>

                    <div class="mt-2 h-[10px] bg-surface">
                        <div class="h-full bg-accent"
                             style="width: {{ $mostSessions > 0 ? round($row->sessions / $mostSessions * 100) : 0 }}%"></div>
                    </div>

                    <p class="mt-2 text-xs opacity-60">
                        {{ $row->clients }} klientów · {{ Money::format($row->revenue) }}
                    </p>
                </div>
            @endforeach
        </section>

        <section>
            <div class="flex items-baseline gap-2.5 border-b-2 border-divider pb-2.5">
                <h3>Ostatnie zmiany</h3>
                <x-btn variant="ghost" class="ml-auto text-xs" :href="route('admin.activity.index')">Cały log →</x-btn>
            </div>

            @forelse ($entries as $entry)
                <div class="flex flex-wrap items-baseline gap-2 border-b border-divider py-2.5" wire:key="entry-{{ $entry->getKey() }}">
                    <span class="min-w-[92px] text-xs font-extrabold opacity-55">
                        {{ $entry->happened_at->format('d.m · H:i') }}
                    </span>
                    <span class="text-sm">{{ $entry->action }}</span>
                    <span class="text-xs opacity-60">{{ $entry->context }}</span>
                    <span class="ml-auto text-xs opacity-55">{{ $entry->actor_name }}</span>
                </div>
            @empty
                <p class="mt-4 text-[13px] text-muted">Log jest pusty — nikt jeszcze nic nie zmienił.</p>
            @endforelse
        </section>
    </div>
</div>
