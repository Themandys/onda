<section data-track-list wire:init="load">
    <header class="genre-hero mb-6 rounded-3xl p-6 text-ink sm:p-8" style="--hue: {{ (int) $details['hue'] }}">
        <p class="eyebrow text-ink/70!">Género · lo más popular en {{ $region }}</p>
        <h1 class="page-title mt-2">{{ $details['name'] }}</h1>
        <p class="mt-2 text-sm text-ink/75">{{ $details['tagline'] }}</p>

        <div class="mt-5 flex min-h-12 flex-wrap items-center gap-2">
            @if ($tracks)
                @include('partials.list-actions', ['label' => $details['name']])
            @endif
        </div>
    </header>

    <nav class="no-scrollbar -mx-1 mb-5 flex gap-1.5 overflow-x-auto px-1" aria-label="Otros géneros">
        @foreach ($genres as $slug => $other)
            <a
                href="{{ route('genres.show', $slug) }}"
                wire:navigate
                @class([
                    'shrink-0 rounded-full px-3.5 py-1.5 text-xs font-semibold transition',
                    'btn-primary' => $slug === $genre,
                    'btn-secondary' => $slug !== $genre,
                ])
                @if ($slug === $genre) aria-current="page" @endif
            >{{ $other['name'] }}</a>
        @endforeach
    </nav>

    @if (! $loaded)
        @include('partials.track-skeleton', ['rows' => 8])
        <p class="sr-only" role="status">Cargando canciones…</p>
    @elseif ($tracks)
        <ul class="glass rounded-2xl p-2">
            @foreach ($tracks as $index => $track)
                @include('partials.track-row', [
                    'track' => $track,
                    'index' => $index,
                    'favorite' => in_array($track['video_id'], $this->favoriteIds, true),
                    'playlists' => $this->playlists,
                ])
            @endforeach
        </ul>
    @else
        <div class="glass flex flex-wrap items-center justify-between gap-3 rounded-2xl px-5 py-6 text-sm text-ink/70" role="status">
            No se pudo cargar la lista de {{ $details['name'] }} ahora mismo.
            <button type="button" wire:click="$refresh" class="btn-secondary rounded-full px-3.5 py-1.5 text-xs font-medium transition">Reintentar</button>
        </div>
    @endif
</section>
