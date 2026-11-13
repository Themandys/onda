<div>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Estadísticas de escucha</p>
            <h1 class="mt-1 page-title text-ink">Resumen mensual</h1>
            <p class="mt-1 text-sm text-ink/60">Tus canciones y artistas más escuchados mes a mes.</p>
        </div>

        @if (count($availableMonths) > 0)
            <div class="flex flex-wrap items-center gap-1.5" role="tablist" aria-label="Seleccionar mes">
                @foreach ($availableMonths as $m)
                    <button
                        type="button"
                        wire:key="month-{{ $m['value'] }}"
                        wire:click="selectMonth(@js($m['value']))"
                        @class([
                            'rounded-full px-3.5 py-1.5 text-xs font-semibold transition',
                            'btn-primary shadow-sm' => $selectedMonth === $m['value'],
                            'btn-secondary' => $selectedMonth !== $m['value'],
                        ])
                    >{{ $m['label'] }}</button>
                @endforeach
            </div>
        @endif
    </header>

    {{-- Tarjeta principal del Resumen Mensual --}}
    <section class="collection-hero glass mb-8 rounded-3xl p-5 sm:p-6" aria-label=" Estadísticas de {{ $selectedMonthLabel }}">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="inline-block rounded-full bg-ink/8 px-3 py-1 text-xs font-semibold text-ink/80">
                    {{ $selectedMonthLabel }}
                </span>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-ink">
                    Tu Onda de {{ $selectedMonthLabel }}
                </h2>
            </div>

            @if ($stats['top_track'])
                <div class="flex items-center gap-3 rounded-2xl bg-ink/6 p-2.5">
                    <button
                        type="button"
                        data-zoom-cover="{{ json_encode($stats['top_track']) }}"
                        class="cover-zoom-btn relative size-14 shrink-0 overflow-hidden rounded-xl bg-ink/10"
                        title="Ver portada en grande"
                    >
                        <img
                            src="{{ \App\Services\MusicStreamingService::thumbnail($stats['top_track']['thumbnail_url'], 226) }}"
                            alt=""
                            referrerpolicy="no-referrer"
                            class="size-full object-cover"
                        >
                    </button>
                    <div class="min-w-0 pr-2">
                        <p class="eyebrow">Canción #1 del mes</p>
                        <p class="truncate text-sm font-semibold text-ink">{{ $stats['top_track']['title'] }}</p>
                        <p class="truncate text-xs text-ink/60">
                            {{ $stats['top_track']['artist'] }} · {{ $stats['top_track']['play_count'] }} {{ $stats['top_track']['play_count'] === 1 ? 'escucha' : 'escuchas' }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <div class="recap-stats-grid mt-5">
            <div class="recap-stat-card">
                <p class="text-xs font-medium text-ink/60">Reproducciones</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-ink tabular-nums">{{ $stats['total_plays'] }}</p>
                <p class="text-[11px] text-ink/60">en este mes</p>
            </div>

            <div class="recap-stat-card">
                <p class="text-xs font-medium text-ink/60">Tiempo de escucha</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-ink tabular-nums">{{ $stats['total_minutes'] }} min</p>
                <p class="text-[11px] text-ink/60">{{ round($stats['total_minutes'] / 60, 1) }} h estimadas</p>
            </div>

            <div class="recap-stat-card">
                <p class="text-xs font-medium text-ink/60">Canciones distintas</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-ink tabular-nums">{{ $stats['unique_tracks'] }}</p>
                <p class="text-[11px] text-ink/60">en tu rotación</p>
            </div>

            <div class="recap-stat-card">
                <p class="text-xs font-medium text-ink/60">Artistas escuchados</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-ink tabular-nums">{{ $stats['unique_artists'] }}</p>
                <p class="text-[11px] text-ink/60">en {{ $selectedMonthLabel }}</p>
            </div>
        </div>
    </section>

    @if ($topArtists !== [])
        <section class="mb-8" aria-labelledby="recap-artists-heading">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h2 id="recap-artists-heading" class="section-title text-ink">Artistas más escuchados</h2>
                    <p class="text-sm text-ink/60">Pulsa un artista para ver su perfil.</p>
                </div>
            </div>

            <div class="artist-grid">
                @foreach ($topArtists as $rank => $artistItem)
                    <a
                        href="{{ route('artist', ['name' => $artistItem['name']]) }}"
                        wire:navigate
                        class="artist-card glass flex items-center gap-3 rounded-2xl p-2.5"
                    >
                        <img
                            src="{{ \App\Services\MusicStreamingService::thumbnail($artistItem['thumbnail_url'], 120) }}"
                            alt="{{ $artistItem['name'] }}"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            class="size-12 shrink-0 rounded-full object-cover"
                        >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-ink">#{{ $rank + 1 }} {{ $artistItem['name'] }}</p>
                            <p class="truncate text-xs text-ink/60">
                                {{ $artistItem['plays'] }} {{ $artistItem['plays'] === 1 ? 'reproducción' : 'reproducciones' }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section data-track-list aria-labelledby="recap-tracks-heading">
        <div class="mb-2.5 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 id="recap-tracks-heading" class="section-title text-ink">Canciones más escuchadas</h2>
                <p class="text-sm text-ink/60">Ordenadas por número de reproducciones en {{ $selectedMonthLabel }}.</p>
            </div>

            @if ($topTracks !== [])
                <div class="flex items-center gap-2">
                    <button type="button" data-enqueue-all class="section-link">Añadir a la fila</button>
                    <button type="button" data-play-all class="section-link">Reproducir todo</button>
                </div>
            @endif
        </div>

        @if ($topTracks !== [])
            <ul class="glass rounded-2xl p-2">
                @foreach ($topTracks as $index => $track)
                    @include('partials.track-row', [
                        'track' => $track,
                        'index' => $index,
                        'scope' => 'recap',
                        'meta' => $track['play_count'] === 1 ? '1 escucha' : $track['play_count'].' escuchas',
                        'favorite' => in_array($track['video_id'], $this->favoriteIds, true),
                        'playlists' => $this->playlists,
                    ])
                @endforeach
            </ul>
        @else
            <div class="glass rounded-2xl px-5 py-10 text-center text-sm text-ink/60">
                Todavía no hay canciones registradas en {{ $selectedMonthLabel }}. Reproduce cualquier canción para empezar a construir tu resumen mensual.
            </div>
        @endif
    </section>
</div>
