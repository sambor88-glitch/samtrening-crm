<x-app-layout :title="$title">
    <p class="mb-3 text-[11px] tracking-[0.16em] text-accent uppercase">{{ $story }}</p>
    <h1 class="text-[clamp(34px,5vw,54px)]">{{ $title }}</h1>

    <x-empty-state title="Ten ekran jest w budowie.">
        Powstaje w zgłoszeniu {{ $story }}. Pasek górny i nawigacja już działają.
    </x-empty-state>
</x-app-layout>
