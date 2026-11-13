<section data-track-list>
    <a href="{{ route('library') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium text-ink/60 transition hover:bg-ink/6 hover:text-ink">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
        Biblioteca
    </a>

    <header class="collection-hero glass flex flex-col gap-5 rounded-3xl p-5 sm:flex-row sm:items-end sm:gap-6 sm:p-6">
        @include('partials.cover-mosaic', ['covers' => $covers, 'size' => 400, 'class' => 'w-36 shrink-0 sm:w-44'])

        <div class="min-w-0 flex-1">
            <p class="eyebrow">Playlist</p>

            <input
                type="text"
                wire:model="name"
                wire:change="rename"
                maxlength="60"
                aria-label="Nombre de la playlist"
                title="Pulsa para cambiar el nombre"
                class="title-field -mx-1.5 mt-1 px-1.5 py-0.5 page-title text-ink"
            >

            @error('name')
                <p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>
            @enderror

            <p class="mt-1.5 text-sm text-ink/60">
                {{ trans_choice('{0} Sin canciones todavía|{1} :count canción|[2,*] :count canciones', count($tracks)) }}
                · actualizada {{ $playlist->updated_at->locale('es')->diffForHumans() }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($tracks)
                    @include('partials.list-actions', ['label' => 'la playlist'])
                @endif

                <button
                    type="button"
                    wire:click="deletePlaylist"
                    wire:confirm="¿Eliminar la playlist «{{ $playlist->name }}»? Las canciones no se borran de tus favoritos."
                    class="btn-danger rounded-full px-3.5 py-2 text-xs font-medium text-ink/60 transition"
                >Eliminar playlist</button>
            </div>
        </div>
    </header>

    @if ($tracks)
        <ul class="glass mt-5 rounded-2xl p-2">
            @foreach ($tracks as $index => $track)
                @include('partials.track-row', [
                    'track' => $track,
                    'index' => $index,
                    'favorite' => in_array($track['video_id'], $this->favoriteIds, true),
                    'playlists' => $this->playlists,
                    'removeId' => $track['id'],
                ])
            @endforeach
        </ul>
    @else
        <p class="glass mt-5 rounded-3xl px-5 py-10 text-center text-sm text-ink/60">
            Esta playlist está vacía. Añade canciones con el botón + de cualquier lista.
        </p>
    @endif
</section>
