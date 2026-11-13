<section data-track-list>
    <a href="{{ route('library') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium text-ink/60 transition hover:bg-ink/6 hover:text-ink">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
        Biblioteca
    </a>

    <header class="collection-hero glass flex flex-col gap-5 rounded-3xl p-5 sm:flex-row sm:items-end sm:gap-6 sm:p-6">
        @include('partials.cover-mosaic', ['covers' => $covers, 'size' => 400, 'class' => 'w-36 shrink-0 sm:w-44'])

        <div class="min-w-0 flex-1">
            <p class="eyebrow">Colección</p>
            <h1 class="mt-1 page-title text-ink">Favoritos</h1>

            <p class="mt-1.5 text-sm text-ink/60">
                {{ trans_choice('{0} Sin canciones todavía|{1} :count canción|[2,*] :count canciones', count($tracks)) }}
                · las más recientes primero
            </p>

            @if ($tracks)
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @include('partials.list-actions', ['label' => 'tus favoritos'])
                </div>
            @endif
        </div>
    </header>

    @if ($tracks)
        <ul class="glass mt-5 rounded-2xl p-2">
            @foreach ($tracks as $index => $track)
                @include('partials.track-row', [
                    'track' => $track,
                    'index' => $index,
                    'favorite' => true,
                    'playlists' => $this->playlists,
                ])
            @endforeach
        </ul>
    @else
        <p class="glass mt-5 rounded-3xl px-5 py-10 text-center text-sm text-ink/60">
            Aún no tienes favoritos. Pulsa el corazón de una canción para guardarla.
        </p>
    @endif
</section>
