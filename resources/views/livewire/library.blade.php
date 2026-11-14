<div>
    <header class="mb-6">
        <h1 class="page-title text-ink">Tu biblioteca</h1>
        <p class="mt-2 text-sm text-ink/60">Tus favoritos, tus playlists y lo que has guardado para escuchar sin conexión.</p>
    </header>

    <section aria-label="Tus colecciones">
        <div class="collection-grid">
            <a href="{{ route('favorites') }}" wire:navigate class="collection-card glass">
                <div class="relative">
                    @include('partials.cover-mosaic', ['covers' => $favoriteCovers])

                    <span class="chip-btn absolute bottom-2 left-2 text-heart!" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="size-4" fill="currentColor"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                    </span>
                </div>

                <div class="min-w-0 px-1 pb-1">
                    <p class="truncate text-sm font-semibold text-ink">Favoritos</p>
                    <p class="truncate text-xs text-ink/60">
                        {{ trans_choice('{0} Sin canciones todavía|{1} :count canción|[2,*] :count canciones', count($this->favoriteIds)) }}
                    </p>
                </div>
            </a>

            @foreach ($collections as $collection)
                <a
                    wire:key="collection-{{ $collection->id }}"
                    href="{{ route('playlists.show', $collection) }}"
                    wire:navigate
                    class="collection-card glass"
                >
                    @include('partials.cover-mosaic', ['covers' => $collection->tracks->pluck('thumbnail_url')->all()])

                    <div class="min-w-0 px-1 pb-1">
                        <p class="truncate text-sm font-semibold text-ink">{{ $collection->name }}</p>
                        <p class="truncate text-xs text-ink/60">
                            {{ trans_choice('{0} Vacía|{1} :count canción|[2,*] :count canciones', $collection->tracks_count) }}
                            · {{ $collection->updated_at->locale('es')->diffForHumans() }}
                        </p>
                    </div>
                </a>
            @endforeach

            <form wire:submit="createPlaylist" class="collection-card glass border-dashed!">
                <div class="cover-mosaic bg-none! shadow-none! ring-1 ring-ink/10 ring-inset" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="size-1/4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                </div>

                <div class="flex items-center gap-1.5 pb-1">
                    <input
                        type="text"
                        wire:model="newPlaylist"
                        placeholder="Nueva playlist…"
                        maxlength="60"
                        aria-label="Nombre de la nueva playlist"
                        class="min-w-0 flex-1 rounded-lg bg-transparent px-1 py-1 text-sm font-medium text-ink outline-none placeholder:font-normal placeholder:text-ink/45 focus:bg-ink/5"
                    >
                    <button type="submit" class="btn-primary grid size-7 shrink-0 place-items-center rounded-full transition hover:scale-105" aria-label="Crear playlist">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    </button>
                </div>
            </form>
        </div>

        @error('newPlaylist')
            <p class="mt-3 text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror
    </section>

    {{-- Canciones guardadas sin internet (Offline en la app) --}}
    <section data-track-list class="mt-10" aria-labelledby="offline-heading">
        <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 id="offline-heading" class="flex items-center gap-2 section-title text-ink">
                    <span>Disponibles sin conexión</span>
                    <span class="rounded-full bg-ink/8 px-2.5 py-0.5 text-xs font-medium text-ink/70 tabular-nums">
                        {{ $offlineTracks->count() }}
                    </span>
                </h2>
                <p class="text-sm text-ink/60">Guardadas en este navegador: suenan aunque no tengas internet.</p>
            </div>

            <div class="flex items-center gap-2">
                {{-- Enlace normal (sin wire:navigate): el modo sin conexión es una página aparte, sin Livewire. --}}
                <a href="{{ route('offline') }}" class="section-link" title="Pantalla que funciona sin internet con las canciones guardadas en este dispositivo">Abrir modo sin conexión</a>

                @if ($offlineTracks->isNotEmpty())
                    <button
                        type="button"
                        data-play-all
                        class="section-link"
                    >Reproducir todo</button>
                @endif
            </div>
        </div>

        @if ($offlineTracks->isEmpty())
            <p class="glass rounded-2xl px-5 py-6 text-sm text-ink/60">
                Todavía no has guardado ninguna canción. Abre el menú «···» de cualquier canción y elige «Guardar sin internet».
            </p>
        @else
            <ul class="glass rounded-2xl p-2">
                @foreach ($offlineTracks as $index => $item)
                    @php
                        $trackData = $item->toTrack();
                        $payload = Arr::only($trackData, ['video_id', 'title', 'artist', 'thumbnail_url']);
                    @endphp
                    <li
                        wire:key="offline-{{ $item->video_id }}"
                        data-track="{{ json_encode($trackData) }}"
                        data-video-id="{{ $item->video_id }}"
                        data-offline="true"
                        class="track-row group flex items-center gap-2 rounded-xl px-2 py-1"
                    >
                        <button
                            type="button"
                            data-play
                            class="grid w-6 shrink-0 place-items-center py-2 text-center text-xs text-ink/60 tabular-nums outline-none"
                            aria-label="Reproducir {{ $item->title }}"
                        >
                            <span class="track-index">{{ $index + 1 }}</span>
                            <span class="track-eq" aria-hidden="true"><i></i><i></i><i></i></span>
                        </button>

                        <button
                            type="button"
                            data-zoom-cover
                            class="cover-zoom-btn relative size-11 shrink-0 overflow-hidden rounded-lg bg-ink/10 transition"
                            title="Ver portada en grande"
                        >
                            <img
                                src="{{ \App\Services\MusicStreamingService::thumbnail($item->thumbnail_url, 120) }}"
                                alt=""
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                class="size-full object-cover"
                            >
                        </button>

                        <div class="min-w-0 flex-1 py-1 pl-1">
                            <button type="button" data-play class="track-title flex max-w-full items-center gap-1.5 truncate text-left text-sm font-medium text-ink">
                                <span class="truncate">{{ $item->title }}</span>
                                <span class="offline-pill">Sin conexión</span>
                            </button>
                            <p class="truncate text-xs text-ink/60">{{ $item->artist }}</p>
                        </div>

                        <span class="hidden shrink-0 rounded-full bg-ink/6 px-2.5 py-0.5 text-[11px] font-medium text-ink/70 tabular-nums sm:block">
                            {{ $item->formattedSize() }}
                        </span>

                        <a
                            href="{{ route('download', ['videoId' => $item->video_id, 'title' => $item->title, 'artist' => $item->artist]) }}"
                            download
                            class="action-btn grid size-9 shrink-0 place-items-center rounded-full text-ink/60 transition"
                            title="Descargar archivo de audio en mi dispositivo"
                            aria-label="Descargar {{ $item->title }} en dispositivo"
                        >
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>

                        <button
                            type="button"
                            wire:click="removeOfflineTrack(@js($item->video_id))"
                            class="action-btn grid size-9 shrink-0 place-items-center rounded-full text-ink/60 transition hover:text-danger"
                            title="Quitar del almacenamiento sin internet"
                            aria-label="Quitar {{ $item->title }} de offline"
                        >
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($excluded->isNotEmpty())
        <section class="mt-10" aria-labelledby="excluded-heading">
            <div class="mb-2">
                <h2 id="excluded-heading" class="section-title text-ink">Canciones excluidas</h2>
                <p class="text-sm text-ink/60">No aparecen en el inicio, las sugerencias ni las búsquedas. Restáuralas cuando quieras.</p>
            </div>

            <ul class="glass rounded-2xl p-2">
                @foreach ($excluded as $track)
                    <li wire:key="excluded-{{ $track->video_id }}" class="flex items-center gap-3 rounded-2xl px-2 py-1.5">
                        <img
                            src="{{ \App\Services\MusicStreamingService::thumbnail($track->thumbnail_url, 120) }}"
                            alt=""
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            class="size-10 shrink-0 rounded-lg bg-ink/10 object-cover opacity-70 ring-1 ring-ink/10 grayscale"
                        >

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink/80">{{ $track->title }}</p>
                            <p class="truncate text-xs text-ink/60">{{ $track->artist }}</p>
                        </div>

                        <button
                            type="button"
                            wire:click="restoreTrack(@js($track->video_id))"
                            class="btn-secondary shrink-0 rounded-full px-3 py-1.5 text-xs font-medium transition"
                        >Restaurar</button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
