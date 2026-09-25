{{-- Claude's connector asks here before it gets a token — SC-68, docs/CLAUDE-CONNECTOR.md.
     Passport renders this with $client, $user, $scopes and $authToken. A trainer gets the refusal
     and no approve form: the connector is the owner's, and /mcp checks that again on every call. --}}
@php($isOwner = $user->is_owner && $user->status === \App\Domain\Team\Enums\UserStatus::Active)

<x-guest-layout title="Połączenie z Claude" kicker="Asystent w telefonie">
    <x-slot:headline>Claude pyta<br>o dostęp do CRM.</x-slot:headline>
    <x-slot:lead>Po zgodzie zapytasz go w rozmowie o salda, zaległości i zarobki, a on odpowie z danych studia.</x-slot:lead>

    @if ($isOwner)
        <h1 class="mb-1.5 text-[34px]">Połączyć?</h1>
        <p class="mb-5 text-sm text-muted">„{{ $client->name }}” prosi o dostęp do CRM na Twoim koncie.</p>

        <div class="mb-5 space-y-2.5 text-sm">
            <p class="font-semibold">Claude będzie mógł:</p>
            <ul class="list-disc space-y-1 pl-5">
                <li>czytać listę klientów, stawki, salda i ostatnie sesje,</li>
                <li>sprawdzać zaległości, podsumowanie miesiąca i zarobki trenerów,</li>
                <li>przygotować raport CSV dla księgowej (link ważny 15 minut).</li>
            </ul>
            <p class="font-semibold">Nie zobaczy:</p>
            <ul class="list-disc space-y-1 pl-5">
                <li>przeciwwskazań zdrowotnych, notatek z treningów ani danych opiekuna.</li>
            </ul>
            <p class="text-muted">Dane, o które zapytasz, przechodzą przez Anthropic. Dostęp wygasa po 30 dniach bez użycia.</p>
        </div>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <x-btn type="submit" variant="primary" block class="px-3.5 py-2.5">Połącz z Claude →</x-btn>
        </form>
    @else
        <h1 class="mb-1.5 text-[34px]">Nie tym kontem.</h1>
        <p class="mb-5 text-sm text-muted">Połączenie z Claude jest tylko dla właściciela studia.</p>
    @endif

    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="mt-2.5">
        @csrf
        @method('DELETE')
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <x-btn type="submit" variant="ghost" block class="text-xs">Anuluj</x-btn>
    </form>
</x-guest-layout>
