<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? 'Vonage Dialler' }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-vonage-mist font-sans text-vonage-ink antialiased">
        <header class="bg-vonage-black text-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <a href="{{ route('phone') }}" wire:navigate class="flex items-center gap-3">
                    <x-vonage-logo class="h-7 w-auto" />
                    <span class="text-lg font-semibold tracking-[0.25em]">VONAGE</span>
                    <span class="rounded-full border border-white/20 px-2 py-0.5 text-xs font-medium tracking-wide text-white/70">Dialler</span>
                </a>

                <nav class="flex items-center gap-1 text-sm font-medium">
                    <a href="{{ route('phone') }}" wire:navigate
                        @class(['rounded-full px-4 py-2 transition', 'bg-white text-vonage-black' => request()->routeIs('phone'), 'text-white/70 hover:text-white' => ! request()->routeIs('phone')])>
                        Phone
                    </a>
                    <a href="{{ route('settings') }}" wire:navigate
                        @class(['rounded-full px-4 py-2 transition', 'bg-white text-vonage-black' => request()->routeIs('settings'), 'text-white/70 hover:text-white' => ! request()->routeIs('settings')])>
                        Settings
                    </a>
                </nav>
            </div>
            <div class="vonage-gradient h-1"></div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10">
            {{ $slot }}
        </main>
    </body>
</html>
