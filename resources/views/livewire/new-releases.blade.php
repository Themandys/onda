<section data-track-list wire:init="load">
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow">Recién publicado</p>
            <h1 class="page-title mt-1.5 text-ink">Novedades</h1>
            <p class="mt-2 text-sm text-ink/60">
                Canciones que han salido en {{ $months === 1 ? 'el último mes' : "los últimos {$months} meses" }}, de la más reciente a la más antigua.
            </p>
        </div>

        @if ($tracks)
            <div class="flex flex-wrap items-center gap-2">
                @include('partials.list-actions', ['label' => 'las novedades'])
            </div>
        @endif
    </header>

    @if (! $loaded)
        @include('partials.track-skeleton', ['rows' => 8])
        <p class="sr-only" role="status">Cargando novedades…</p>
    @elseif ($tracks)
        <ul class="glass rounded-2xl p-2">
            @foreach ($tracks as $index => $track)
                @include('partials.track-row', [
                    'track' => $track,
                    'index' => $index,
                    'meta' => $track['released_label'],
                    'favorite' => in_array($track['video_id'], $this->favoriteIds, true),
                    'playlists' => $this->playlists,
                ])
            @endforeach
        </ul>
    @else
        <div class="glass flex flex-wrap items-center justify-between gap-3 rounded-2xl px-5 py-6 text-sm text-ink/70" role="status">
            Ahora mismo no hay novedades que mostrar.
            <button type="button" wire:click="$refresh" class="btn-secondary rounded-full px-3.5 py-1.5 text-xs font-medium transition">Reintentar</button>
        </div>
    @endif
</section>
