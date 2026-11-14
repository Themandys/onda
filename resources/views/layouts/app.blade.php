@php
    use App\Models\User;

    $authUser = auth()->user();
    $themeKey = $authUser?->themeKey() ?? User::DEFAULT_THEME;
    $theme = User::THEMES[$themeKey];
    $genres = (array) config('music.charts.genres', []);

    // Enlaces principales: ruta => [texto, rutas que lo marcan como activo, trazo del icono].
    $navigation = [
        'home' => ['Inicio', ['home'], 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
        'search' => ['Buscar', ['search', 'artist'], 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z'],
        'new-releases' => ['Novedades', ['new-releases'], 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z'],
        'library' => ['Biblioteca', ['library', 'favorites', 'playlists.*'], 'M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122'],
        'recap' => ['Resumen', ['recap'], 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es" class="{{ $theme['scheme'] }}" data-theme="{{ $themeKey }}" data-scheme="{{ $theme['scheme'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="{{ $theme['scheme'] }}">
        <meta name="theme-color" content="{{ $theme['paper'] }}">
        {{-- No enviar la URL de la app a los hosts de portadas ni al proxy de audio. --}}
        <meta name="referrer" content="same-origin">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        @include('partials.pwa')

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.theme')
    </head>
    <body class="min-h-dvh font-sans antialiased">
        {{-- Barra lateral (escritorio) --}}
        <aside class="app-sidebar glass" aria-label="Navegación">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5 px-5 pt-5 pb-4 text-lg font-semibold tracking-tight text-ink">
                @include('partials.brand-mark')
                {{ config('app.name') }}
            </a>

            <div class="side-scroll">
                <nav class="flex flex-col gap-0.5" aria-label="Principal">
                    @foreach ($navigation as $route => [$label, $matches, $icon])
                        <a
                            href="{{ route($route) }}"
                            wire:navigate
                            class="side-link"
                            @if (request()->routeIs(...$matches)) aria-current="page" @endif
                        >
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>

                @if ($genres)
                    <p class="side-heading">Géneros</p>
                    <nav class="flex flex-col gap-0.5" aria-label="Géneros">
                        @foreach ($genres as $slug => $genre)
                            <a
                                href="{{ route('genres.show', $slug) }}"
                                wire:navigate
                                class="side-link"
                                @if (request()->routeIs('genres.show') && request()->route('genre') === $slug) aria-current="page" @endif
                            >
                                <span class="side-dot mx-1.5" style="--hue: {{ (int) $genre['hue'] }}" aria-hidden="true"></span>
                                {{ $genre['name'] }}
                            </a>
                        @endforeach
                    </nav>
                @endif

                <p class="side-heading">Tu música</p>
                <livewire:nav-library />
            </div>
        </aside>

        <div class="app-main">
            <header class="sticky top-0 z-20 bg-paper/75 backdrop-blur-xl">
                <div class="mx-auto flex w-full max-w-6xl items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 text-base font-semibold tracking-tight text-ink lg:hidden">
                        @include('partials.brand-mark')
                        <span class="max-sm:sr-only">{{ config('app.name') }}</span>
                    </a>

                    @unless (request()->routeIs('search'))
                        <form
                            role="search"
                            class="search-box min-w-0 flex-1 text-sm sm:max-w-sm"
                            x-data="{ term: '' }"
                            x-on:submit.prevent="term.trim() && Livewire.navigate(@js(route('search', absolute: false)) + '?q=' + encodeURIComponent(term.trim()))"
                        >
                            <svg viewBox="0 0 24 24" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="{{ $navigation['search'][2] }}" /></svg>
                            <input type="search" x-model="term" placeholder="Buscar canciones o artistas" autocomplete="off" aria-label="Buscar música">
                        </form>
                    @endunless

                    @auth
                        <div
                            class="relative ml-auto"
                            x-data="{ open: false, avatarUrl: @js($authUser->avatarUrl()), avatarSymbol: @js($authUser->avatarSymbol()) }"
                            x-on:click.outside="open = false"
                            x-on:keydown.escape="open = false"
                            x-on:avatar-changed.window="
                                const change = Array.isArray($event.detail) ? $event.detail[0] : $event.detail;
                                avatarUrl = change.url;
                                avatarSymbol = change.symbol;
                            "
                        >
                            <button
                                type="button"
                                x-on:click="open = ! open"
                                x-bind:aria-expanded="open"
                                class="avatar size-9 text-sm transition hover:scale-105"
                                aria-label="Tu cuenta"
                                aria-haspopup="true"
                                title="{{ $authUser->name }}"
                            >
                                <template x-if="avatarUrl"><img x-bind:src="avatarUrl" alt="" referrerpolicy="no-referrer"></template>
                                <span x-show="! avatarUrl" x-text="avatarSymbol">{{ $authUser->hasImageAvatar() ? '' : $authUser->avatarSymbol() }}</span>
                            </button>

                            <div
                                x-cloak
                                x-show="open"
                                x-transition.origin.top.right
                                class="playlist-dropdown absolute top-11 right-0 z-40 w-64 rounded-2xl p-1.5"
                            >
                                <div class="flex items-center gap-3 px-2.5 pt-2 pb-2.5">
                                    <span class="avatar size-10 text-sm">
                                        <template x-if="avatarUrl"><img x-bind:src="avatarUrl" alt="" referrerpolicy="no-referrer"></template>
                                        <span x-show="! avatarUrl" x-text="avatarSymbol"></span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-ink">{{ $authUser->name }}</p>
                                        <p class="truncate text-xs text-ink/60">{{ $authUser->bio ?: $authUser->email }}</p>
                                    </div>
                                </div>

                                <div class="menu-divider"></div>

                                <a href="{{ route('profile') }}" wire:navigate x-on:click="open = false" class="menu-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                    Perfil y apariencia
                                </a>

                                <a href="{{ route('recap') }}" wire:navigate x-on:click="open = false" class="menu-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $navigation['recap'][2] }}" /></svg>
                                    Resumen mensual
                                </a>

                                <div class="menu-divider"></div>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="menu-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>
                                        Cerrar sesión
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>

                {{-- Navegación de móvil y tableta --}}
                <nav class="no-scrollbar flex gap-1 overflow-x-auto px-3 pb-2.5 sm:px-5 lg:hidden" aria-label="Principal">
                    @foreach ($navigation as $route => [$label, $matches])
                        <a
                            href="{{ route($route) }}"
                            wire:navigate
                            class="nav-pill"
                            @if (request()->routeIs(...$matches)) aria-current="page" @endif
                        >{{ $label }}</a>
                    @endforeach
                </nav>
            </header>

            {{-- El padding inferior deja sitio al reproductor anclado. --}}
            <main class="mx-auto w-full max-w-6xl px-4 pt-3 pb-52 sm:px-6 sm:pt-5 lg:px-8">
                {{ $slot }}
            </main>
        </div>

        {{--
            @persist conserva este bloque (componente, <audio> y estado JS) entre
            páginas cuando se navega con wire:navigate: la música no se corta.
        --}}
        @persist('player')
            <livewire:player />
        @endpersist
    </body>
</html>
