<nav class="flex flex-col gap-0.5" aria-label="Tu música">
    <a
        href="{{ route('favorites') }}"
        wire:navigate
        class="side-link"
        @if ($current === 'favorites') aria-current="page" @endif
    >
        <svg viewBox="0 0 24 24" class="size-5 text-heart" fill="currentColor" aria-hidden="true"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
        <span class="min-w-0 flex-1 truncate">Favoritos</span>
        @if ($favorites > 0)
            <span class="text-xs text-ink/45 tabular-nums">{{ $favorites }}</span>
        @endif
    </a>

    @foreach ($playlists as $playlist)
        <a
            wire:key="nav-playlist-{{ $playlist->id }}"
            href="{{ route('playlists.show', $playlist) }}"
            wire:navigate
            class="side-link"
            @if ($current === 'playlist:'.$playlist->id) aria-current="page" @endif
        >
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h6.75M17.25 12.75v6.75m0-6.75 3.75-1.125V15" /></svg>
            <span class="min-w-0 flex-1 truncate">{{ $playlist->name }}</span>
            <span class="text-xs text-ink/45 tabular-nums">{{ $playlist->tracks_count }}</span>
        </a>
    @endforeach

    <a href="{{ route('library') }}" wire:navigate class="side-link text-ink/45!">
        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
        Nueva playlist
    </a>
</nav>
