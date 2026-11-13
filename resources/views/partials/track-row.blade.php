{{--
    Fila de canción. Variables:
      $track      array  video_id, title, artist, thumbnail_url [, duration]
      $index      int    posición en la lista
      $favorite   bool   ¿está en favoritos?
      $playlists  iterable de Playlist para el menú "añadir a"
      $removeId   ?int   id de PlaylistTrack: muestra "quitar de la playlist"
      $scope      ?string prefijo de wire:key cuando la página tiene varias listas
      $meta       ?string texto corto junto a la duración (p. ej. fecha de salida)

    El reproductor (JS) lee `data-track` y monta la cola con todas las filas
    del contenedor [data-track-list] más cercano.
--}}
@php
    $payload = Arr::only($track, ['video_id', 'title', 'artist', 'thumbnail_url']);
    $duration = (int) ($track['duration'] ?? 0);
@endphp

<li
    wire:key="{{ $scope ?? 'track' }}-{{ $track['video_id'] }}"
    data-track="{{ json_encode($payload + ['duration' => $duration]) }}"
    data-video-id="{{ $track['video_id'] }}"
    class="track-row group flex items-center gap-2 rounded-xl px-2 py-1 transition-colors"
>
    <button
        type="button"
        data-play
        class="grid w-7 shrink-0 place-items-center py-2 text-center text-xs text-ink/45 tabular-nums outline-none"
        aria-label="Reproducir {{ $track['title'] }}"
    >
        <span class="track-index">{{ $index + 1 }}</span>
        <span class="track-eq" aria-hidden="true"><i></i><i></i><i></i></span>
    </button>

    <button
        type="button"
        data-zoom-cover
        class="cover-zoom-btn relative size-11 shrink-0 overflow-hidden rounded-lg bg-ink/10 transition"
        title="Ver portada en grande"
        aria-label="Ver portada de {{ $track['title'] }} en grande"
    >
        <img
            src="{{ \App\Services\MusicStreamingService::thumbnail($track['thumbnail_url'], 120) }}"
            alt=""
            loading="lazy"
            referrerpolicy="no-referrer"
            class="size-full object-cover"
        >
        <span class="cover-zoom-badge" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="size-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
            </svg>
        </span>
    </button>

    <div class="min-w-0 flex-1 py-1.5">
        <button
            type="button"
            data-play
            class="track-title flex max-w-full items-center gap-1.5 truncate text-left text-sm font-medium text-ink outline-none"
        >
            <span class="truncate">{{ $track['title'] }}</span>
            <span class="offline-pill" title="Guardada para escuchar sin conexión">Sin conexión</span>
        </button>

        @if (filled($track['artist']))
            <a
                href="{{ route('artist', ['name' => $track['artist']]) }}"
                wire:navigate
                class="block w-fit max-w-full truncate text-xs text-ink/60 transition hover:text-ink hover:underline"
                title="Ver el perfil de {{ $track['artist'] }}"
            >{{ $track['artist'] }}</a>
        @endif
    </div>

    @isset($meta)
        <span class="chip hidden shrink-0 sm:inline-flex">{{ $meta }}</span>
    @endisset

    <button
        type="button"
        data-enqueue
        class="action-btn track-quick hidden size-9 shrink-0 place-items-center rounded-full transition md:grid"
        title="Añadir a la fila de reproducción"
        aria-label="Añadir {{ $track['title'] }} a la fila de reproducción"
    >
        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3.75 6.75h10.5M3.75 12h10.5m-10.5 5.25h6.75M17.25 12v6m-3-3h6" />
        </svg>
    </button>

    <button
        type="button"
        wire:click="toggleFavorite(@js($payload))"
        @class([
            'action-btn track-quick grid size-9 shrink-0 place-items-center rounded-full transition',
            'text-heart!' => $favorite,
        ])
        title="{{ $favorite ? 'Quitar de favoritos' : 'Añadir a favoritos' }}"
        aria-label="{{ $favorite ? 'Quitar de favoritos' : 'Añadir a favoritos' }}"
        aria-pressed="{{ $favorite ? 'true' : 'false' }}"
    >
        <svg viewBox="0 0 24 24" class="size-5" fill="{{ $favorite ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
        </svg>
    </button>

    @if ($duration > 0)
        <span class="hidden w-11 shrink-0 text-right text-xs text-ink/45 tabular-nums sm:block">{{ sprintf('%d:%02d', intdiv($duration, 60), $duration % 60) }}</span>
    @endif

    {{-- Más opciones --}}
    <div class="relative shrink-0" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
        <button
            type="button"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open"
            class="action-btn grid size-9 place-items-center rounded-full transition"
            title="Más opciones"
            aria-label="Más opciones para {{ $track['title'] }}"
            aria-haspopup="true"
        >
            <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true">
                <circle cx="5" cy="12" r="1.6" /><circle cx="12" cy="12" r="1.6" /><circle cx="19" cy="12" r="1.6" />
            </svg>
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition.origin.top.right
            class="playlist-dropdown absolute top-10 right-0 z-30 max-h-80 w-64 overflow-y-auto rounded-2xl p-1.5"
        >
            <button type="button" data-enqueue x-on:click="open = false" class="menu-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.75 6.75h10.5M3.75 12h10.5m-10.5 5.25h6.75M17.25 12v6m-3-3h6" /></svg>
                Añadir a la fila
            </button>

            <button type="button" data-save-offline x-on:click="open = false" class="menu-item">
                <svg viewBox="0 0 24 24" class="offline-icon-save" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" /></svg>
                <svg viewBox="0 0 24 24" class="offline-icon-saved hidden" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <span class="offline-menu-label truncate">Guardar sin internet (en la app)</span>
            </button>

            <a
                href="{{ route('download', ['videoId' => $track['video_id'], 'title' => $track['title'], 'artist' => $track['artist']]) }}"
                download
                x-on:click="open = false"
                class="menu-item"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Descargar en mi dispositivo
            </a>

            <div class="menu-divider"></div>
            <p class="menu-label">Añadir a playlist</p>

            @forelse ($playlists as $option)
                <button
                    type="button"
                    wire:key="add-{{ $scope ?? 'track' }}-{{ $track['video_id'] }}-{{ $option->id }}"
                    wire:click="addToPlaylist({{ $option->id }}, @js($payload))"
                    x-on:click="open = false"
                    class="menu-item"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    <span class="truncate">{{ $option->name }}</span>
                </button>
            @empty
                <a href="{{ route('library') }}" wire:navigate class="menu-item text-ink/60!">
                    Crea tu primera playlist en la biblioteca
                </a>
            @endforelse

            <div class="menu-divider"></div>

            @isset($removeId)
                <button type="button" wire:click="removeFromPlaylist({{ $removeId }})" x-on:click="open = false" class="menu-item menu-item-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    Quitar de la playlist
                </button>
            @endisset

            <button
                type="button"
                wire:click="excludeTrack(@js($payload))"
                x-on:click="open = false"
                class="menu-item menu-item-danger"
                title="Excluir: no volver a mostrar esta canción"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                Excluir esta canción
            </button>
        </div>
    </div>
</li>
