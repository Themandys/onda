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

        {{-- Sin sesión no se sabe de quién es la apariencia: se usa la que recuerda este navegador. --}}
        @include('partials.theme', ['restore' => true])
    </head>
    <body class="min-h-dvh font-sans antialiased">
        <div id="onda-backdrop" class="backdrop" data-ready="false" aria-hidden="true"></div>
        <div class="ambient pointer-events-none fixed inset-0 -z-10" aria-hidden="true"></div>

        <main class="mx-auto grid min-h-dvh w-full max-w-sm place-items-center px-4 py-10">
            <div class="w-full">
                <p class="mb-7 flex items-center justify-center gap-2.5 text-xl font-semibold tracking-tight text-ink">
                    @include('partials.brand-mark')
                    {{ config('app.name') }}
                </p>

                {{-- Tras recargar por una sesión caducada (ver resources/js/app.js). --}}
                <p
                    x-data="{
                        expired: false,
                        init() {
                            try {
                                this.expired = sessionStorage.getItem('onda:expired') === '1';
                                sessionStorage.removeItem('onda:expired');
                            } catch {}
                        },
                    }"
                    x-show="expired"
                    x-cloak
                    role="status"
                    class="glass mb-4 rounded-2xl px-4 py-3 text-sm text-ink/80"
                >
                    La página llevaba un rato abierta y se ha actualizado. Vuelve a intentarlo.
                </p>

                {{ $slot }}
            </div>
        </main>
    </body>
</html>
