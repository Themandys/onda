{{--
    Tarjeta de canción (inicio). Variables:
      $track     array  video_id, title, artist, thumbnail_url [, duration]
      $favorite  bool   ¿está en favoritos?
      $scope     string prefijo de wire:key (una misma canción puede salir en dos tiras)

    Igual que la fila, expone `data-track` para que el reproductor monte la
    cola con todas las tarjetas del contenedor [data-track-list].
--}}
@php
    $payload = Arr::only($track, ['video_id', 'title', 'artist', 'thumbnail_url']);
@endphp

<li
    wire:key="{{ $scope }}-{{ $track['video_id'] }}"
    data-track="{{ json_encode($payload + ['duration' => (int) ($track['duration'] ?? 0)]) }}"
    data-video-id="{{ $track['video_id'] }}"
    class="track-card group flex min-w-0 flex-col gap-2"
>
    <div class="track-card-cover">
        <button type="button" data-play class="block size-full outline-none" aria-label="Reproducir {{ $track['title'] }}">
            <img
                src="{{ \App\Services\MusicStreamingService::thumbnail($track['thumbnail_url'], 226) }}"
                alt=""
                loading="lazy"
                referrerpolicy="no-referrer"
            >
        </button>

        <div class="track-card-actions">
            <button
                type="button"
                wire:click="toggleFavorite(@js($payload))"
                @class(['chip-btn', 'text-heart!' => $favorite])
                title="{{ $favorite ? 'Quitar de favoritos' : 'Añadir a favoritos' }}"
                aria-label="{{ $favorite ? 'Quitar de favoritos' : 'Añadir a favoritos' }}"
                aria-pressed="{{ $favorite ? 'true' : 'false' }}"
            >
                <svg viewBox="0 0 24 24" class="size-4" fill="{{ $favorite ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                </svg>
            </button>

            <button
                type="button"
                wire:click="excludeTrack(@js($payload))"
                class="chip-btn"
                title="Excluir: no volver a mostrar esta canción"
                aria-label="Excluir {{ $track['title'] }}"
            >
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </button>
        </div>

        <button type="button" data-play class="crystal track-card-play size-10" tabindex="-1" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="size-5 translate-x-px" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.5-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14Z" /></svg>
        </button>
    </div>

    <div class="min-w-0 px-0.5">
        <div class="flex items-center gap-1.5">
            <span class="track-eq shrink-0" aria-hidden="true"><i></i><i></i><i></i></span>
            <button type="button" data-play class="track-title block min-w-0 truncate text-left text-sm font-medium text-ink outline-none">{{ $track['title'] }}</button>
        </div>

        @if (filled($track['artist']))
            <a
                href="{{ route('artist', ['name' => $track['artist']]) }}"
                wire:navigate
                class="block truncate text-xs text-ink/60 transition hover:text-ink hover:underline"
            >{{ $track['artist'] }}</a>
        @endif
    </div>
</li>
