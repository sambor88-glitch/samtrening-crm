@props([
    'user',
    'admin' => false,
])

{{-- Lime bar with black text, like the contact bar on samtrening.com. --}}
<div class="flex flex-wrap items-center gap-4 bg-accent px-4 py-[7px] text-[11px] tracking-[0.06em] text-bg uppercase">
    <span class="font-extrabold">Studio otwarte</span>
    <span class="opacity-60">Plac Na Groblach 23, Kraków · Pon–Niedz 5:30—23:00</span>

    <span class="ml-auto flex items-center gap-3.5">
        {{-- The role switch exists only for the owner; a trainer gets no markup at all. --}}
        @if ($user->is_owner)
            <span class="opacity-60">Widok</span>
            @foreach (['dashboard' => 'Trener', 'admin.dashboard' => 'Admin'] as $route => $label)
                @php($current = ($route === 'admin.dashboard') === $admin)
                <a href="{{ route($route) }}" @if ($current) aria-current="page" @endif class="chrome-action relative px-px pt-0.5 pb-[7px] font-extrabold tracking-[0.08em] text-bg no-underline hover:text-bg">
                    {{ $label }}
                    @if ($current)
                        <span class="absolute inset-x-0 bottom-0 h-[3px] bg-bg"></span>
                    @endif
                </a>
            @endforeach
            <span class="opacity-35">|</span>
        @endif

        <span class="font-extrabold">{{ $user->name }}</span>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="chrome-action cursor-pointer p-0.5 font-extrabold tracking-[0.08em] uppercase opacity-55 hover:opacity-100">Wyloguj</button>
        </form>
    </span>
</div>
