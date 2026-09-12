@props([
    'title' => null,
    'variant' => 'surface', // surface | accent — ekran 3 dostaje pełnoekranową limonkę
    'kicker' => null,
    'headline' => null,
    'lead' => null,
])
<!DOCTYPE html>
<html lang="pl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        {{-- The access screens split in two: the studio on the left, the form on the right.
             Below 640 px the columns stack — the form ends up under the brand panel. --}}
        <div class="grid min-h-screen grid-cols-[repeat(auto-fit,minmax(320px,1fr))]">
            <div @class([
                'flex flex-col gap-8 px-10 py-12',
                'border-r-2 border-divider bg-surface' => $variant !== 'accent',
                'bg-accent text-bg' => $variant === 'accent',
            ])>
                <p class="text-[26px] font-extrabold tracking-[-0.02em]">SAM<span @class(['text-accent-400' => $variant !== 'accent'])>·</span>TRENING</p>

                <div class="flex flex-1 flex-col justify-center">
                    <p @class(["mb-3.5 text-[11px] tracking-[0.16em] uppercase", "text-accent-400" => $variant !== "accent", "opacity-85" => $variant === "accent"])>{{ $kicker }}</p>
                    <p class="display text-[clamp(30px,4.4vw,46px)]">{{ $headline }}</p>
                    <p @class(["mt-[18px] max-w-[38ch] text-sm", "opacity-65" => $variant !== "accent", "opacity-90" => $variant === "accent"])>{{ $lead }}</p>
                </div>

                <p @class(["text-[11px] leading-[1.9] tracking-[0.1em] uppercase", "opacity-45" => $variant !== "accent", "opacity-70" => $variant === "accent"])>
                    Plac Na Groblach 23, Kraków<br>
                    Pon–Niedz · 5:30—23:00
                </p>
            </div>

            <div class="flex items-center px-10 py-12">
                <div class="w-full max-w-[380px]">{{ $slot }}</div>
            </div>
        </div>

        <x-toast />

        @livewireScripts
    </body>
</html>
