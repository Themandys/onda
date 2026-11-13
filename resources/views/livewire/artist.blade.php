@php
    use Illuminate\Support\Number;

    $displayName = $info['name'] ?? $name;
    $photo = $info['photo_url'] ?? null;
    $facts = array_filter([
        ($info['fans'] ?? 0) > 0 ? Number::withLocale('es', fn () => Number::abbreviate($info['fans'], precision: 1)).' seguidores' : null,
        ($info['albums'] ?? 0) > 0 ? trans_choice('{1} :count álbum|[2,*] :count álbumes', $info['albums']) : null,
        $total > 0 ? trans_choice('{1} :count canción|[2,*] :count canciones', $total) : null,
    ]);
@endphp

<section data-track-list wire:init="load">
    <header class="artist-hero">
        @if ($photo)
            <img src="{{ $photo }}" alt="" referrerpolicy="no-referrer" class="artist-hero-photo">
        @endif

        <div class="p-6 sm:p-8">
            <p class="eyebrow text-white/70!">Artista</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight text-balance sm:text-6xl">{{ $displayName }}</h1>

            @if (! $loaded)
                <div class="mt-4 h-4 w-48 rounded-full bg-white/15" aria-hidden="true"></div>
                <p class="sr-only" role="status">Cargando el perfil de {{ $displayName }}…</p>
            @else
                @if (filled($info['summary'] ?? null))
                    <p class="mt-3 max-w-2xl text-sm text-white/85">{{ $info['summary'] }}</p>
                @endif

                @if ($facts)
                    <p class="mt-1.5 text-sm text-white/65">{{ implode(' · ', $facts) }}</p>
                @endif
            @endif

            <div class="mt-5 flex min-h-12 flex-wrap items-center gap-2">
                @if ($tracks)
                    @include('partials.list-actions', ['label' => 'las canciones de '.$displayName])
                @endif
            </div>
        </div>
    </header>

    <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        {{-- Canciones --}}
        <div class="min-w-0">
            <h2 class="section-title mb-2 text-ink">Canciones</h2>

            @if (! $loaded)
                @include('partials.track-skeleton', ['rows' => 8])
            @elseif ($error)
                <div class="glass flex flex-wrap items-center justify-between gap-3 rounded-2xl px-5 py-5 text-sm text-ink/70" role="alert">
                    {{ $error }}
                    <button type="button" wire:click="$refresh" class="btn-secondary rounded-full px-3.5 py-1.5 text-xs font-medium transition">Reintentar</button>
                </div>
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

                @if ($total > count($tracks))
                    <div class="mt-3 text-center">
                        <button type="button" wire:click="showAllTracks" class="btn-secondary rounded-full px-4 py-2 text-xs font-medium transition">
                            <span wire:loading.remove wire:target="showAllTracks">Ver las {{ $total }} canciones</span>
                            <span wire:loading wire:target="showAllTracks">Cargando…</span>
                        </button>
                    </div>
                @endif
            @else
                <p class="glass rounded-2xl px-5 py-8 text-center text-sm text-ink/60">
                    No se encontraron canciones de «{{ $displayName }}».
                </p>
            @endif
        </div>

        {{-- Quién es --}}
        <aside class="flex min-w-0 flex-col gap-6" aria-label="Sobre {{ $displayName }}">
            <div>
                <h2 class="section-title mb-2 text-ink">Acerca de</h2>

                <div class="glass rounded-2xl p-5">
                    @if (! $loaded)
                        <div class="skeleton h-3.5 w-full"></div>
                        <div class="skeleton mt-2 h-3.5 w-11/12"></div>
                        <div class="skeleton mt-2 h-3.5 w-4/5"></div>
                        <div class="skeleton mt-2 h-3.5 w-2/3"></div>
                    @elseif (filled($info['bio'] ?? null))
                        <div x-data="{ expanded: false }">
                            <p class="text-sm leading-relaxed text-ink/80" x-bind:class="expanded || 'line-clamp-6'">{{ $info['bio'] }}</p>

                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                <button type="button" x-on:click="expanded = ! expanded" class="section-link -ml-2" x-text="expanded ? 'Ver menos' : 'Leer más'">Leer más</button>

                                <a href="{{ $info['bio_url'] }}" target="_blank" rel="noopener noreferrer" class="text-xs text-ink/45 transition hover:text-ink hover:underline">
                                    Fuente: Wikipedia
                                </a>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-ink/60">Todavía no hay una biografía disponible para este artista.</p>
                    @endif
                </div>
            </div>

            @if ($loaded && ($info['related'] ?? []))
                <div>
                    <h2 class="section-title mb-2 text-ink">Artistas parecidos</h2>

                    <ul class="flex flex-col gap-0.5">
                        @foreach ($info['related'] as $related)
                            <li wire:key="related-{{ md5($related['name']) }}">
                                <a href="{{ route('artist', ['name' => $related['name']]) }}" wire:navigate class="side-link">
                                    <span class="avatar size-9 text-xs">
                                        @if ($related['photo_url'])
                                            <img src="{{ $related['photo_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer">
                                        @else
                                            {{ mb_substr($related['name'], 0, 1) }}
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-ink">{{ $related['name'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</section>
