<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <x-layout.top-bar :user="auth()->user()" :admin="$admin" />
        <x-layout.app-nav :admin="$admin" :navigation="$navigation" />

        @if ($tickerEnabled)
            <x-layout.ticker />
        @endif

        <main class="mx-auto max-w-[1240px] px-4 pt-8 pb-20">
            {{ $slot }}
        </main>

        @unless ($admin)
            <livewire:dialogs.log-session-dialog />
            <livewire:trainer.reminders />
        @endunless

        <x-toast />

        @livewireScripts
    </body>
</html>
