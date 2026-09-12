@props([
    'admin' => false,
    'navigation' => [], // route name => label
])

<div class="sticky top-0 z-20 flex flex-wrap items-center gap-4 border-b-2 border-divider bg-bg px-4 py-3">
    <a href="{{ route($admin ? 'admin.dashboard' : 'dashboard') }}" class="chrome-brand mr-6 text-[18px] leading-tight font-extrabold tracking-[-0.02em] text-ink no-underline hover:text-ink">
        SAM<span class="text-accent">·</span>TRENING
        <span class="block text-[9px] font-normal tracking-[0.22em] opacity-50">{{ $admin ? 'CRM · PANEL ADMINA' : 'CRM · PANEL TRENERA' }}</span>
    </a>

    <nav class="mr-auto flex flex-wrap gap-0.5" aria-label="Nawigacja główna">
        @foreach ($navigation as $route => $label)
            {{-- "clients.index" also covers "clients.show", so the client card lights up "Klienci". --}}
            @php($current = request()->routeIs(Str::before($route, '.index').'*'))
            <a href="{{ route($route) }}" @if ($current) aria-current="page" @endif class="nav-link relative px-3 pt-2.5 pb-3 text-[13px] font-extrabold tracking-[0.01em] no-underline {{ $current ? 'text-accent' : 'text-ink hover:text-accent' }}">
                {{ $label }}
                @if ($current)
                    <span class="absolute inset-x-3 bottom-0.5 h-[3px] bg-accent"></span>
                @endif
            </a>
        @endforeach
    </nav>

    {{-- The dialogs listening for these events arrive with SC-23 and SC-36. --}}
    @if ($admin)
        <x-btn variant="primary" class="tracking-[0.02em]" x-data x-on:click="$dispatch('invite-trainer')">＋ Zaproś trenera</x-btn>
    @else
        <x-btn variant="primary" class="tracking-[0.02em]" x-data x-on:click="$dispatch('log-session')">＋ Wbij sesję</x-btn>
    @endif
</div>
