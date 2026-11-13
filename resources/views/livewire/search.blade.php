<div>
    <header class="mb-5">
        <h1 class="page-title text-ink">Buscar</h1>
        <p class="mt-2 text-sm text-ink/60">Canciones y artistas de todo el catálogo.</p>
    </header>

    <label class="search-box px-4! py-3! text-base">
        <svg viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
            <path d="m21 21-5.2-5.2m0 0a7.5 7.5 0 1 0-10.6-10.6 7.5 7.5 0 0 0 10.6 10.6Z" />
        </svg>

        <input
            type="search"
            wire:model.live.debounce.400ms="query"
            placeholder="¿Qué quieres escuchar?"
            autocomplete="off"
            autofocus
            aria-label="Buscar música"
        >

        <svg wire:loading wire:target="query" viewBox="0 0 24 24" class="size-5 shrink-0 animate-spin" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M12 3a9 9 0 1 0 9 9" />
        </svg>
    </label>

    @if ($error)
        <div class="mt-6 rounded-2xl border border-danger/25 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
            {{ $error }}
        </div>
    @elseif ($results)
        @if ($artists)
            <section class="mt-7" aria-labelledby="artists-heading">
                <h2 id="artists-heading" class="section-title mb-2.5 text-ink">Artistas</h2>

                <div class="artist-grid">
                    @foreach ($artists as $artist)
                        <a
                            wire:key="artist-{{ md5($artist['name']) }}"
                            href="{{ route('artist', ['name' => $artist['name']]) }}"
                            wire:navigate
                            class="artist-card glass flex items-center gap-3 rounded-2xl p-2.5"
                        >
                            <span class="avatar size-12 text-sm">
                                <img
                                    src="{{ \App\Services\MusicStreamingService::thumbnail($artist['thumbnail_url'], 120) }}"
                                    alt=""
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                >
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-ink">{{ $artist['name'] }}</span>
                                <span class="block truncate text-xs text-ink/60">Ver perfil</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section data-track-list class="mt-7 transition-opacity" wire:loading.class="opacity-50" wire:target="query" aria-labelledby="songs-heading">
            <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
                <h2 id="songs-heading" class="section-title text-ink">
                    Canciones <span class="text-sm font-normal text-ink/45">{{ count($results) }}</span>
                </h2>

                <div class="flex items-center gap-1">
                    <button type="button" data-enqueue-all class="section-link">Añadir todo a la fila</button>
                    <button type="button" data-play-all class="section-link">Reproducir todo</button>
                </div>
            </div>

            <ul class="glass rounded-2xl p-2">
                @foreach ($results as $index => $track)
                    @include('partials.track-row', [
                        'track' => $track,
                        'index' => $index,
                        'favorite' => in_array($track['video_id'], $this->favoriteIds, true),
                        'playlists' => $this->playlists,
                    ])
                @endforeach
            </ul>
        </section>
    @elseif (mb_strlen(trim($query)) >= 2)
        <p class="mt-16 text-center text-sm text-ink/60" role="status">Sin resultados para «{{ $query }}».</p>
    @else
        <section class="mt-8" aria-labelledby="browse-heading">
            <h2 id="browse-heading" class="section-title mb-3 text-ink">Explora por género</h2>

            <div class="genre-grid">
                @foreach ($genres as $slug => $genre)
                    <a
                        href="{{ route('genres.show', $slug) }}"
                        wire:navigate
                        class="genre-tile text-ink"
                        style="--hue: {{ (int) $genre['hue'] }}"
                    >
                        <span class="text-base font-semibold tracking-tight">{{ $genre['name'] }}</span>
                        <span class="max-w-[80%] text-xs text-ink/70">{{ $genre['tagline'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
