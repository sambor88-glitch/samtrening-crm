@php
    use App\Domain\Team\Enums\UserStatus;
    use App\Support\Money;
@endphp

<div>
    <div class="mb-5 flex flex-wrap items-end gap-5">
        <h1 class="min-w-[260px] flex-1 text-[clamp(32px,4.4vw,46px)]">Trzy osoby.<br>Nie statyści.</h1>
        <x-btn variant="primary" x-on:click="$dispatch('invite-trainer')">＋ Zaproś trenera</x-btn>
    </div>

    <p class="mb-6 max-w-[56ch] text-muted">
        Zaproszenie idzie mailem, a trener sam ustawia hasło. Do aktywacji konto nie widzi żadnych
        danych klientów.
    </p>

    <x-data-table :columns="['Trener', 'Specjalizacja', 'Status', 'Klienci', 'Sesje / '.$monthName, 'Obrót', '']">
        @foreach ($rows as $row)
            <tr wire:key="trainer-{{ $row->trainer->getKey() }}">
                <td data-label="Trener">
                    <div>
                        <div class="font-extrabold">
                            {{ $row->trainer->name }}
                            @if ($row->trainer->is_owner)
                                <span class="text-xs font-normal opacity-55">· właściciel</span>
                            @endif
                        </div>
                        <div class="text-xs opacity-55">{{ $row->trainer->email }}</div>
                    </div>
                </td>

                <td data-label="Specjalizacja" class="text-[13px]">{{ $row->trainer->specialty ?: '—' }}</td>

                <td data-label="Status">
                    @switch ($row->trainer->status)
                        @case (UserStatus::Active)
                            <x-tag>Aktywny</x-tag>
                            @break
                        @case (UserStatus::Invited)
                            <x-tag variant="outline">Zaproszenie wysłane</x-tag>
                            @break
                        @default
                            <x-tag variant="accent">Zablokowany</x-tag>
                    @endswitch
                </td>

                <td data-label="Klienci" class="text-[13px]">{{ $row->clients }}</td>
                <td data-label="Sesje" class="text-[13px]">{{ $row->sessions }}</td>
                <td data-label="Obrót" class="font-extrabold">{{ Money::format($row->revenue) }}</td>

                <td data-label="" class="text-right">
                    @if ($row->trainer->status === UserStatus::Invited)
                        <x-btn variant="ghost" class="text-xs"
                               :href="route('admin.trainers.preview', $row->trainer)">Podgląd linku</x-btn>
                        <x-btn variant="ghost" class="text-xs"
                               wire:click="resendInvitation({{ $row->trainer->getKey() }})"
                               wire:loading.attr="disabled" wire:target="resendInvitation({{ $row->trainer->getKey() }})">Ponów zaproszenie</x-btn>
                        <x-btn variant="ghost" class="text-xs"
                               wire:click="accessLink({{ $row->trainer->getKey() }})"
                               wire:loading.attr="disabled" wire:target="accessLink({{ $row->trainer->getKey() }})">Link z ręki</x-btn>
                    @else
                        <x-btn variant="ghost" class="text-xs"
                               wire:click="resetPassword({{ $row->trainer->getKey() }})"
                               wire:loading.attr="disabled" wire:target="resetPassword({{ $row->trainer->getKey() }})">Reset hasła</x-btn>
                        <x-btn variant="ghost" class="text-xs"
                               wire:click="accessLink({{ $row->trainer->getKey() }})"
                               wire:loading.attr="disabled" wire:target="accessLink({{ $row->trainer->getKey() }})">Link z ręki</x-btn>

                        @unless ($row->trainer->is_owner)
                            <x-btn variant="ghost" class="text-xs"
                                   wire:click="toggleBlock({{ $row->trainer->getKey() }}, {{ $row->trainer->status === UserStatus::Blocked ? 'false' : 'true' }})"
                                   wire:loading.attr="disabled"
                                   wire:target="toggleBlock({{ $row->trainer->getKey() }}, {{ $row->trainer->status === UserStatus::Blocked ? 'false' : 'true' }})">
                                {{ $row->trainer->status === UserStatus::Blocked ? 'Aktywuj' : 'Zablokuj' }}
                            </x-btn>
                        @endunless
                    @endif
                </td>
            </tr>

            @if ($linkFor === $row->trainer->getKey() && $link)
                <tr wire:key="link-{{ $row->trainer->getKey() }}">
                    <td data-label="" colspan="6">
                        <div class="border-l-[3px] border-accent bg-surface p-4"
                             x-data="{ skopiowane: false, nieudane: false }">
                            <p class="text-[13px] font-extrabold">
                                Link dla {{ $row->trainer->name }} —
                                {{ $row->trainer->status === UserStatus::Invited ? 'ważny 7 dni' : 'ważny 60 minut' }},
                                działa raz.
                            </p>
                            <p class="mt-1 text-xs text-muted">
                                Przekaż go osobiście, SMS-em albo komunikatorem. Trener ustawi własne hasło —
                                Ty go nie poznasz. Wygenerowanie nowego unieważnia ten.
                            </p>

                            <code x-ref="link"
                                  class="mt-3 block overflow-x-auto bg-bg p-2.5 text-[12px] break-all">{{ $link }}</code>

                            <div class="mt-3 flex flex-wrap gap-2">
                                {{-- Napis zmienia się dopiero po udanym zapisie do schowka: inaczej
                                     mówiłby „Skopiowane" także wtedy, gdy nic się nie skopiowało. --}}
                                <x-btn variant="primary" class="text-xs"
                                       x-on:click="navigator.clipboard.writeText($refs.link.textContent.trim())
                                           .then(() => { nieudane = false; skopiowane = true; setTimeout(() => skopiowane = false, 2500) })
                                           .catch(() => { skopiowane = false; nieudane = true })">
                                    <span x-show="! skopiowane">Kopiuj link</span>
                                    <span x-show="skopiowane" x-cloak>Skopiowane</span>
                                </x-btn>
                                <x-btn variant="ghost" class="text-xs" wire:click="forgetLink">Ukryj</x-btn>
                            </div>

                            <p x-show="nieudane" x-cloak class="mt-2 text-xs text-accent-700">
                                Przeglądarka nie dała dostępu do schowka. Zaznacz link powyżej i skopiuj ręcznie.
                            </p>
                        </div>
                    </td>
                </tr>
            @endif
        @endforeach
    </x-data-table>
</div>
