@php
    $theme = \App\Models\User::THEMES[\App\Models\User::DEFAULT_THEME];
@endphp
<!DOCTYPE html>
<html lang="es" class="{{ $theme['scheme'] }}" data-theme="{{ \App\Models\User::DEFAULT_THEME }}" data-scheme="{{ $theme['scheme'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="{{ $theme['scheme'] }}">
        <meta name="theme-color" content="{{ $theme['paper'] }}">
        <meta name="referrer" content="same-origin">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        @include('partials.pwa')

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.theme')
    </head>
    <body class="min-h-dvh font-sans antialiased">
        <div class="ambient pointer-events-none fixed inset-0 -z-10" aria-hidden="true"></div>

        <main class="mx-auto grid min-h-dvh w-full max-w-sm place-items-center px-4 py-10">
            <div class="w-full">
                <p class="mb-7 flex items-center justify-center gap-2.5 text-xl font-semibold tracking-tight text-ink">
                    @include('partials.brand-mark')
                    {{ config('app.name') }}
                </p>

                {{ $slot }}
            </div>
        </main>
    </body>
</html>
