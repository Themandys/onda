{{--
    Modo sin conexión.

    Una página que funciona sola, sin servidor: el service worker la guarda
    en el dispositivo y la enseña cuando no hay internet. Lista y reproduce
    las canciones que el reproductor guardó en este navegador (IndexedDB);
    no pide nada a la red, así que no lleva Livewire ni datos de la cuenta.
    Todo el comportamiento está en resources/js/offline.js.
--}}
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

        <title>Sin conexión · {{ config('app.name') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        @include('partials.pwa')

        @fonts
        @vite(['resources/css/app.css', 'resources/js/offline.js'])

        {{-- Sin servidor no se sabe de quién es la apariencia: se usa la que recuerda este navegador. --}}
        @include('partials.theme', ['restore' => true])
    </head>
    <body class="min-h-dvh font-sans antialiased">
        <div id="onda-backdrop" class="backdrop" data-ready="false" aria-hidden="true"></div>

        <div data-offline-player data-status="idle" data-network="offline" data-muted="false" class="group/player">
            <div class="ambient pointer-events-none fixed inset-0 -z-10" aria-hidden="true"></div>

            <header class="app-topbar">
                <div class="mx-auto flex w-full max-w-4xl items-center gap-3 px-4 py-3 sm:px-6">
                    <p class="flex items-center gap-2.5 text-base font-semibold tracking-tight text-ink">
                        @include('partials.brand-mark')
                        {{ config('app.name') }}
                    </p>

                    <p class="chip ml-auto gap-1.5" role="status">
                        <span class="size-1.5 rounded-full bg-danger group-data-[network=online]/player:bg-success" aria-hidden="true"></span>
                        <span data-el="network">Sin conexión</span>
                    </p>

                    <a href="{{ route('home') }}" class="btn-primary hidden rounded-full px-4 py-2 text-xs font-semibold transition group-data-[network=online]/player:inline-flex">
                        Volver a Onda
                    </a>
                </div>
            </header>

            <main class="mx-auto w-full max-w-4xl px-4 pt-6 pb-56 sm:px-6">
                <header class="mb-6">
                    <p class="eyebrow">Modo sin conexión</p>
                    <h1 class="page-title mt-1.5 text-ink">Tus canciones descargadas</h1>
                    <p data-el="summary" class="mt-2 text-sm text-ink/60">Buscando las canciones guardadas en este dispositivo…</p>
                </header>

                <div data-el="actions" class="mb-4 hidden flex-wrap items-center gap-2">
                    <button data-el="play-all" type="button" class="btn-primary flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold transition active:scale-[0.98]">
                        <svg viewBox="0 0 24 24" class="size-4" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.5-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14Z" /></svg>
                        Reproducir
                    </button>

                    <button data-el="shuffle-all" type="button" class="btn-secondary flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium transition active:scale-[0.98]">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h3.2a4 4 0 0 1 3.3 1.75l5 7.5A4 4 0 0 0 17.8 18H21m0 0-2.5-2.5M21 18l-2.5 2.5M3 17h3.2a4 4 0 0 0 3.3-1.75l.6-.9M21 6h-3.2a4 4 0 0 0-3.3 1.75l-.6.9M21 6l-2.5-2.5M21 6l-2.5 2.5" /></svg>
                        Aleatorio
                    </button>
                </div>

                <ul data-el="list" class="glass hidden rounded-2xl p-2" aria-label="Canciones descargadas"></ul>

                <div data-el="empty" class="glass hidden rounded-3xl px-6 py-10 text-center">
                    <p class="text-base font-semibold text-ink">Todavía no hay canciones guardadas aquí</p>
                    <p class="mx-auto mt-1.5 max-w-md text-sm text-ink/60">
                        Cuando tengas conexión, abre el menú «···» de una canción y elige «Guardar sin internet». Las que guardes en este dispositivo aparecerán en esta pantalla.
                    </p>
                </div>

                <p data-el="notice" role="alert" class="mt-4 hidden text-center text-sm text-danger"></p>
            </main>

            {{-- Reproductor --}}
            <div class="pointer-events-none fixed inset-x-0 bottom-0 z-30 px-3 pb-3 sm:px-5 sm:pb-4">
                <div class="relative mx-auto w-full max-w-4xl">
                    <div class="player-glow" aria-hidden="true"></div>

                    <section class="glass pointer-events-auto relative rounded-3xl px-3 pt-2.5 pb-3 sm:px-5" aria-label="Reproductor">
                        <div class="flex items-center gap-3 text-[11px] text-ink/60 tabular-nums">
                            <span data-el="current" class="w-9 text-right">0:00</span>
                            <input data-el="seek" type="range" min="0" max="1000" step="1" value="0" disabled class="range min-w-0 flex-1" aria-label="Posición de la canción">
                            <span data-el="duration" class="w-9">0:00</span>
                        </div>

                        <div class="mt-1 grid grid-cols-1 items-center gap-2.5 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:gap-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="relative grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-ink/10 sm:size-14">
                                    <svg viewBox="0 0 24 24" class="size-5 text-ink/45" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                    <img data-el="cover" alt="" class="absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-500">
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p data-el="title" class="truncate text-sm font-semibold text-ink">Nada sonando</p>
                                    <p data-el="artist" class="truncate text-xs text-ink/60">Elige una canción de la lista</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-center gap-2.5">
                                <button data-el="shuffle" data-active="false" type="button" title="Aleatorio" aria-label="Reproducción aleatoria" aria-pressed="false" class="queue-toggle-btn px-0!">
                                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h3.2a4 4 0 0 1 3.3 1.75l5 7.5A4 4 0 0 0 17.8 18H21m0 0-2.5-2.5M21 18l-2.5 2.5M3 17h3.2a4 4 0 0 0 3.3-1.75l.6-.9M21 6h-3.2a4 4 0 0 0-3.3 1.75l-.6.9M21 6l-2.5-2.5M21 6l-2.5 2.5" /></svg>
                                </button>

                                <button data-el="prev" type="button" aria-label="Anterior" title="Anterior" class="crystal size-10 shrink-0">
                                    <svg viewBox="0 0 24 24" class="size-5 -scale-x-100" fill="currentColor" aria-hidden="true"><path d="M5 5.6v12.8a1 1 0 0 0 1.55.83L16 13v5a1 1 0 1 0 2 0V6a1 1 0 1 0-2 0v5L6.55 4.77A1 1 0 0 0 5 5.6Z" /></svg>
                                </button>

                                <button data-el="toggle" type="button" aria-label="Reproducir" class="crystal crystal-strong size-14 shrink-0">
                                    <svg viewBox="0 0 24 24" class="size-6 translate-x-px group-data-[status=playing]/player:hidden" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.5-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14Z" /></svg>
                                    <svg viewBox="0 0 24 24" class="hidden size-6 group-data-[status=playing]/player:block" fill="currentColor" aria-hidden="true"><rect x="6" y="4.5" width="4.2" height="15" rx="1.3" /><rect x="13.8" y="4.5" width="4.2" height="15" rx="1.3" /></svg>
                                </button>

                                <button data-el="next" type="button" aria-label="Siguiente" title="Siguiente" class="crystal size-10 shrink-0">
                                    <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M5 5.6v12.8a1 1 0 0 0 1.55.83L16 13v5a1 1 0 1 0 2 0V6a1 1 0 1 0-2 0v5L6.55 4.77A1 1 0 0 0 5 5.6Z" /></svg>
                                </button>

                                <button data-el="repeat" data-active="false" type="button" title="Repetir la lista" aria-label="Repetir la lista" aria-pressed="false" class="queue-toggle-btn px-0!">
                                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 2.5 20.5 6 17 9.5M3.5 11V9.5A3.5 3.5 0 0 1 7 6h13.5M7 21.5 3.5 18 7 14.5M20.5 13v1.5A3.5 3.5 0 0 1 17 18H3.5" /></svg>
                                </button>
                            </div>

                            <div class="hidden items-center justify-end gap-1 md:flex">
                                <button data-el="mute" type="button" aria-label="Silenciar" class="action-btn grid size-9 shrink-0 place-items-center rounded-full transition">
                                    <svg viewBox="0 0 24 24" class="size-5 group-data-[muted=true]/player:hidden" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4V5ZM15.5 8.5a5 5 0 0 1 0 7M18.4 5.6a9 9 0 0 1 0 12.8" /></svg>
                                    <svg viewBox="0 0 24 24" class="hidden size-5 group-data-[muted=true]/player:block" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4V5ZM22 9.5l-5 5M17 9.5l5 5" /></svg>
                                </button>
                                <input data-el="volume" type="range" min="0" max="100" step="1" value="80" class="range w-24" aria-label="Volumen">
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <audio data-el="audio" preload="auto"></audio>
        </div>

        {{-- Fila de canción: offline.js la clona y la rellena con textContent (nunca con HTML). --}}
        <template id="offline-track-row">
            <li class="track-row group flex items-center gap-2 rounded-xl px-2 py-1">
                <button type="button" data-play class="grid w-7 shrink-0 place-items-center py-2 text-center text-xs text-ink/45 tabular-nums outline-none">
                    <span class="track-index"></span>
                    <span class="track-eq" aria-hidden="true"><i></i><i></i><i></i></span>
                </button>

                <span class="relative grid size-11 shrink-0 place-items-center overflow-hidden rounded-lg bg-ink/10 text-ink/45">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    <img data-cover alt="" loading="lazy" referrerpolicy="no-referrer" class="absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-300">
                </span>

                <button type="button" data-play class="min-w-0 flex-1 py-1.5 text-left outline-none">
                    <span data-title class="track-title block truncate text-sm font-medium text-ink"></span>
                    <span data-artist class="block truncate text-xs text-ink/60"></span>
                </button>

                <span data-size class="chip hidden shrink-0 sm:inline-flex"></span>
                <span data-duration class="hidden w-11 shrink-0 text-right text-xs text-ink/45 tabular-nums sm:block"></span>
            </li>
        </template>
    </body>
</html>
