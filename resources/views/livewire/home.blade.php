<div wire:init="loadDiscovery">
    <header class="mb-8">
        <p
            class="eyebrow"
            x-data
            x-text="new Date().toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' })"
        >&nbsp;</p>

        <h1
            class="page-title mt-1.5 text-ink"
            x-data="{ hour: new Date().getHours() }"
            x-text="(hour >= 6 && hour < 12 ? 'Buenos días' : hour >= 12 && hour < 20 ? 'Buenas tardes' : 'Buenas noches') + @js($name !== '' ? ', '.$name : '')"
        >Hola{{ $name !== '' ? ', '.$name : '' }}</h1>
    </header>

    {{-- Escuchadas recientemente --}}
    <section data-track-list class="mb-10" aria-labelledby="recent-heading">
        <div class="mb-2 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <h2 id="recent-heading" class="section-title text-ink">Escuchadas recientemente</h2>
                <p class="text-sm text-ink/60">Lo último que ha sonado, para retomarlo donde lo dejaste.</p>
            </div>

            @if ($recent)
                <button type="button" data-play-all class="section-link">Reproducir todo</button>
            @endif
        </div>

        @if ($recent)
            <ul class="card-strip">
                @foreach ($recent as $track)
                    @include('partials.track-card', [
                        'track' => $track,
                        'scope' => 'recent',
                        'favorite' => in_array($track['video_id'], $this->favoriteIds, true),
                    ])
                @endforeach
            </ul>
        @else
            <p class="glass rounded-2xl px-5 py-6 text-sm text-ink/60">
                Todavía no has escuchado nada. En cuanto suene una canción, aparecerá aquí.
            </p>
        @endif
    </section>

    {{-- Géneros --}}
    @if ($genres)
        <section class="mb-10" aria-labelledby="genres-heading">
            <div class="mb-3">
                <h2 id="genres-heading" class="section-title text-ink">Explora por género</h2>
                <p class="text-sm text-ink/60">Lo más popular de cada estilo en {{ $region }}.</p>
            </div>

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

    {{-- Sugerencias del día --}}
    <section data-track-list class="mb-10" aria-labelledby="suggestions-heading">
        <div class="mb-2 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <h2 id="suggestions-heading" class="section-title text-ink">Sugerencias de hoy</h2>
                <p class="text-sm text-ink/60">Una selección de lo más escuchado ahora en {{ $region }}. Cambia cada día.</p>
            </div>

            @if ($suggestions)
                <button type="button" data-play-all class="section-link">Reproducir todo</button>
            @endif
        </div>

        @if (! $discoveryLoaded)
            <div class="card-grid" aria-hidden="true">
                @for ($i = 0; $i < 6; $i++)
                    <div class="p-2">
                        <div class="skeleton aspect-square"></div>
                        <div class="skeleton mt-2.5 h-3.5 w-4/5"></div>
                        <div class="skeleton mt-1.5 h-3 w-1/2"></div>
                    </div>
                @endfor
            </div>
            <p class="sr-only" role="status">Cargando sugerencias…</p>
        @elseif ($suggestions)
            <ul class="card-grid">
                @foreach ($suggestions as $track)
                    @include('partials.track-card', [
                        'track' => $track,
                        'scope' => 'suggestion',
                        'favorite' => in_array($track['video_id'], $this->favoriteIds, true),
                    ])
                @endforeach
            </ul>
        @else
            <div class="glass flex flex-wrap items-center justify-between gap-3 rounded-2xl px-5 py-5 text-sm text-ink/70" role="status">
                No se pudieron cargar las sugerencias ahora mismo.
                <button type="button" wire:click="$refresh" class="btn-secondary rounded-full px-3.5 py-1.5 text-xs font-medium transition">Reintentar</button>
            </div>
        @endif
    </section>

    {{-- Novedades --}}
    <section data-track-list aria-labelledby="new-heading">
        <div class="mb-2 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <h2 id="new-heading" class="section-title text-ink">Novedades</h2>
                <p class="text-sm text-ink/60">
                    Canciones que han salido en {{ $months === 1 ? 'el último mes' : "los últimos {$months} meses" }}, de la más reciente a la más antigua.
                </p>
            </div>

            <a href="{{ route('new-releases') }}" wire:navigate class="section-link">Ver todas</a>
        </div>

        @if (! $discoveryLoaded)
            @include('partials.track-skeleton', ['rows' => 5])
        @elseif ($newReleases)
            <ul class="glass rounded-2xl p-2">
                @foreach ($newReleases as $index => $track)
                    @include('partials.track-row', [
                        'track' => $track,
                        'index' => $index,
                        'scope' => 'new',
                        'meta' => $track['released_label'],
                        'favorite' => in_array($track['video_id'], $this->favoriteIds, true),
                        'playlists' => $this->playlists,
                    ])
                @endforeach
            </ul>
        @else
            <p class="glass rounded-2xl px-5 py-6 text-sm text-ink/60" role="status">
                Ahora mismo no hay novedades que mostrar. Vuelve a mirar más tarde.
            </p>
        @endif
    </section>
</div>
