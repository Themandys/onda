{{--
    Reproductor continuo.

    · wire:ignore  → Livewire no vuelve a pintar este bloque: el DOM es del JavaScript.
    · data-status  → idle | loading | playing | paused (lo usa Tailwind para cambiar iconos).
    · --glow-1/2   → colores de la luz ambiental, calculados a partir de la portada.
    · Dos <audio>  → dos "platos": uno suena y en el otro se prepara la canción siguiente,
                     para que el salto sea inmediato y el modo DJ pueda mezclarlas.
--}}
<div wire:ignore data-player data-status="idle" data-muted="false" class="group/player">
    {{-- Luz ambiental de toda la página (detrás del contenido) --}}
    <div class="ambient pointer-events-none fixed inset-0 -z-10" aria-hidden="true"></div>

    {{-- Modal para ver la portada de la canción en grande --}}
    <div
        data-el="cover-modal"
        data-open="false"
        role="dialog"
        aria-modal="true"
        aria-label="Portada de la canción"
        class="cover-modal"
    >
        <div data-el="cover-backdrop" class="cover-modal-backdrop"></div>

        <div class="cover-modal-card glass">
            <button data-el="cover-close" type="button" aria-label="Cerrar portada" class="cover-modal-close">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" />
                </svg>
            </button>

            <div class="cover-modal-frame">
                <img data-el="cover-modal-img" alt="Portada en alta resolución" referrerpolicy="no-referrer" class="cover-modal-img">
            </div>

            <div class="mt-4 min-w-0">
                <h3 data-el="cover-modal-title" class="truncate text-lg font-semibold tracking-tight text-ink">Canción</h3>
                <button
                    data-el="cover-modal-artist"
                    type="button"
                    class="mt-0.5 block max-w-full truncate text-left text-sm font-medium text-ink/70 transition hover:text-ink hover:underline"
                    title="Ver el perfil del artista"
                ></button>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-1.5">
                <button
                    data-el="cover-modal-play"
                    type="button"
                    class="btn-primary inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition active:scale-95"
                >
                    <svg viewBox="0 0 24 24" class="size-3.5" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.5-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14Z" /></svg>
                    <span>Reproducir</span>
                </button>

                <button
                    data-el="cover-modal-enqueue"
                    type="button"
                    class="btn-secondary inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-xs font-medium transition"
                    title="Añadir a la fila de reproducción"
                >
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3.75 6.75h10.5M3.75 12h10.5m-10.5 5.25h6.75M17.25 12v6m-3-3h6" />
                    </svg>
                    A la fila
                </button>

                <button
                    data-el="cover-modal-offline"
                    type="button"
                    class="btn-secondary inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-xs font-medium transition"
                    title="Guardar en la app para escuchar sin internet"
                >
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                    </svg>
                    <span data-el="cover-modal-offline-label">Offline</span>
                </button>

                <a
                    data-el="cover-modal-download"
                    href="#"
                    download
                    class="btn-secondary inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-xs font-medium transition"
                    title="Descargar archivo de audio en mi dispositivo"
                >
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Descargar</span>
                </a>
            </div>
        </div>
    </div>

    <div class="app-player-dock pointer-events-none fixed inset-x-0 bottom-0 z-40">
        <div class="px-3 pb-3 sm:px-6 sm:pb-5 lg:px-8">
            <div class="relative mx-auto max-w-6xl">
                {{-- Resplandor detrás del reproductor --}}
                <div class="player-glow" aria-hidden="true"></div>

                {{-- Panel desplegable: fila de reproducción --}}
                <aside data-el="queue-panel" data-open="false" aria-label="Fila de reproducción" class="queue-panel glass">
                    <div class="queue-panel-header">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold tracking-tight text-ink">Fila de reproducción</h3>
                            <p data-el="queue-subtitle" class="text-xs text-ink/60">La fila está vacía</p>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button data-el="queue-clear" type="button" class="btn-secondary btn-danger rounded-full px-3 py-1 text-xs font-medium transition">
                                Vaciar
                            </button>
                            <button data-el="queue-close" type="button" aria-label="Cerrar fila de reproducción" class="action-btn grid size-8 place-items-center rounded-full transition">
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                                    <path d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <ul data-el="queue-list" class="queue-panel-list"></ul>
                </aside>

                {{-- Panel desplegable: velocidad de reproducción --}}
                <div data-el="speed-panel" data-open="false" role="radiogroup" aria-label="Velocidad de reproducción" class="speed-panel glass">
                    <p class="menu-label">Velocidad</p>

                    @foreach (['0.5' => '0.5×', '0.75' => '0.75×', '1' => 'Normal', '1.25' => '1.25×', '1.5' => '1.5×', '2' => '2×'] as $rate => $label)
                        <button type="button" role="radio" aria-checked="{{ (string) $rate === '1' ? 'true' : 'false' }}" data-speed="{{ $rate }}" class="speed-option">
                            <span>{{ $label }}</span>
                            @if ((string) $rate === '0.5')
                                <span class="text-[11px] opacity-70">Mitad</span>
                            @elseif ((string) $rate === '2')
                                <span class="text-[11px] opacity-70">Doble</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <p
                    data-el="toast"
                    data-show="false"
                    role="status"
                    aria-live="polite"
                    class="playlist-dropdown pointer-events-none absolute -top-3 left-1/2 max-w-[92%] -translate-x-1/2 -translate-y-full truncate rounded-full px-4 py-2 text-xs font-medium text-ink opacity-0 transition duration-300 data-[show=true]:opacity-100"
                ></p>

                <section class="glass pointer-events-auto relative rounded-3xl px-3 pt-2.5 pb-3 sm:px-5" aria-label="Reproductor">
                    {{-- Progreso --}}
                    <div class="flex items-center gap-3 text-[11px] text-ink/60 tabular-nums">
                        <span data-el="current" class="w-9 text-right">0:00</span>
                        <input data-el="seek" type="range" min="0" max="1000" step="1" value="0" disabled class="range min-w-0 flex-1" aria-label="Posición de la canción">
                        <span data-el="duration" class="w-9">0:00</span>
                    </div>

                    <div class="mt-1 grid grid-cols-1 items-center gap-2.5 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:gap-6">
                        {{-- Canción actual --}}
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                data-el="cover-trigger"
                                type="button"
                                title="Ver portada en grande"
                                aria-label="Ver portada de la canción en grande"
                                class="cover-zoom-btn relative grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-ink/10 transition sm:size-14"
                            >
                                <svg viewBox="0 0 24 24" class="size-5 text-ink/45" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m9 9 10.5-3m0 6.55v3.75a2.25 2.25 0 0 1-1.63 2.16l-1.32.38a1.8 1.8 0 1 1-.99-3.47l2.31-.66a2.25 2.25 0 0 0 1.63-2.16Zm0 0V2.25L9 5.25v10.3m0 0v3.75a2.25 2.25 0 0 1-1.63 2.16l-1.32.38a1.8 1.8 0 0 1-.99-3.47l2.31-.66A2.25 2.25 0 0 0 9 15.55Z" />
                                </svg>
                                <img data-el="cover" alt="" referrerpolicy="no-referrer" class="absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-500">
                                <span class="cover-zoom-badge" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" class="size-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
                                    </svg>
                                </span>
                            </button>

                            <div class="min-w-0 flex-1 md:flex-none md:shrink">
                                <p data-el="title" class="truncate text-sm font-semibold text-ink">Nada sonando</p>
                                <button
                                    data-el="artist"
                                    type="button"
                                    class="block max-w-full truncate text-left text-xs text-ink/60 transition hover:text-ink hover:underline"
                                    title="Ver el perfil del artista"
                                >Busca una canción para empezar</button>
                            </div>

                            <button
                                data-el="favorite"
                                data-active="false"
                                type="button"
                                aria-label="Favorito"
                                aria-pressed="false"
                                class="action-btn group/fav grid size-9 shrink-0 place-items-center rounded-full transition group-data-[status=idle]/player:hidden data-[active=true]:text-heart"
                            >
                                <svg viewBox="0 0 24 24" class="size-5 fill-none group-data-[active=true]/fav:fill-current" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                </svg>
                            </button>

                            <button
                                data-el="offline-btn"
                                data-saved="false"
                                type="button"
                                title="Guardar sin internet en la app"
                                aria-label="Guardar sin internet en la app"
                                class="action-btn grid size-9 shrink-0 place-items-center rounded-full transition group-data-[status=idle]/player:hidden data-[saved=true]:text-success"
                            >
                                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                                </svg>
                            </button>
                        </div>

                        {{-- Controles --}}
                        <div class="flex items-center justify-between gap-1 md:justify-center md:gap-2.5">
                            <button
                                data-speed-toggle
                                data-open="false"
                                data-active="false"
                                type="button"
                                title="Velocidad de reproducción"
                                aria-label="Velocidad de reproducción"
                                aria-haspopup="true"
                                class="queue-toggle-btn md:hidden"
                            >
                                <span data-speed-label>1×</span>
                            </button>

                            <button
                                data-el="shuffle"
                                data-active="false"
                                type="button"
                                title="Aleatorio"
                                aria-label="Reproducción aleatoria"
                                aria-pressed="false"
                                class="queue-toggle-btn px-0!"
                            >
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 7h3.2a4 4 0 0 1 3.3 1.75l5 7.5A4 4 0 0 0 17.8 18H21m0 0-2.5-2.5M21 18l-2.5 2.5M3 17h3.2a4 4 0 0 0 3.3-1.75l.6-.9M21 6h-3.2a4 4 0 0 0-3.3 1.75l-.6.9M21 6l-2.5-2.5M21 6l-2.5 2.5" />
                                </svg>
                            </button>

                            <button data-el="prev" type="button" aria-label="Anterior" title="Anterior" class="crystal size-10 shrink-0">
                                <svg viewBox="0 0 24 24" class="size-5 -scale-x-100" fill="currentColor" aria-hidden="true"><path d="M5 5.6v12.8a1 1 0 0 0 1.55.83L16 13v5a1 1 0 1 0 2 0V6a1 1 0 1 0-2 0v5L6.55 4.77A1 1 0 0 0 5 5.6Z" /></svg>
                            </button>

                            <button data-el="toggle" type="button" aria-label="Reproducir" class="crystal crystal-strong size-14 shrink-0">
                                <svg viewBox="0 0 24 24" class="size-6 translate-x-px group-data-[status=loading]/player:hidden group-data-[status=playing]/player:hidden" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.5-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14Z" /></svg>
                                <svg viewBox="0 0 24 24" class="hidden size-6 group-data-[status=playing]/player:block" fill="currentColor" aria-hidden="true"><rect x="6" y="4.5" width="4.2" height="15" rx="1.3" /><rect x="13.8" y="4.5" width="4.2" height="15" rx="1.3" /></svg>
                                <svg viewBox="0 0 24 24" class="hidden size-6 animate-spin group-data-[status=loading]/player:block" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" /></svg>
                            </button>

                            <button data-el="next" type="button" aria-label="Siguiente" title="Siguiente" class="crystal size-10 shrink-0">
                                <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M5 5.6v12.8a1 1 0 0 0 1.55.83L16 13v5a1 1 0 1 0 2 0V6a1 1 0 1 0-2 0v5L6.55 4.77A1 1 0 0 0 5 5.6Z" /></svg>
                            </button>

                            <button
                                data-el="dj"
                                data-active="false"
                                type="button"
                                title="Modo DJ: mezcla una canción con la siguiente y mantiene la fila con música parecida"
                                aria-label="Modo DJ"
                                aria-pressed="false"
                                class="queue-toggle-btn tracking-wide"
                            >DJ</button>

                            <button
                                data-el="queue-toggle"
                                data-open="false"
                                type="button"
                                title="Fila de reproducción"
                                aria-label="Fila de reproducción"
                                class="queue-toggle-btn md:hidden"
                            >
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                                </svg>
                                <span data-el="queue-count-mobile" class="queue-badge">0</span>
                            </button>
                        </div>

                        {{-- Velocidad, fila y volumen --}}
                        <div class="hidden items-center justify-end gap-2 md:flex">
                            <button
                                data-speed-toggle
                                data-open="false"
                                data-active="false"
                                type="button"
                                title="Velocidad de reproducción"
                                aria-label="Velocidad de reproducción"
                                aria-haspopup="true"
                                class="queue-toggle-btn"
                            >
                                <span data-speed-label>1×</span>
                            </button>

                            <button
                                data-el="queue-toggle-desktop"
                                data-open="false"
                                type="button"
                                title="Fila de reproducción"
                                aria-label="Fila de reproducción"
                                class="queue-toggle-btn"
                            >
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                                </svg>
                                <span>Fila</span>
                                <span data-el="queue-count" class="queue-badge">0</span>
                            </button>

                            <button data-el="mute" type="button" aria-label="Silenciar" class="action-btn grid size-9 shrink-0 place-items-center rounded-full transition">
                                <svg viewBox="0 0 24 24" class="size-5 group-data-[muted=true]/player:hidden" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.11 5.64a9 9 0 0 1 0 12.72M16.46 8.29a5.25 5.25 0 0 1 0 7.42M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.7-.5-1.94-1.35a9 9 0 0 1 0-4.8c.24-.84 1.06-1.35 1.94-1.35h2.24Z" /></svg>
                                <svg viewBox="0 0 24 24" class="hidden size-5 group-data-[muted=true]/player:block" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6 4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.7-.5-1.94-1.35a9 9 0 0 1 0-4.8c.24-.84 1.06-1.35 1.94-1.35h2.24Z" /></svg>
                            </button>
                            <input data-el="volume" type="range" min="0" max="100" step="1" value="80" class="range w-20 lg:w-24" aria-label="Volumen">
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <audio data-el="audio" preload="auto"></audio>
    <audio data-el="audio-b" preload="auto"></audio>

    {{-- Aquí el JS inyecta el estilo de la fila que está sonando --}}
    <style data-el="highlight"></style>
</div>

@script
<script>
    /*
    |--------------------------------------------------------------------------
    | Reproductor — JavaScript sin dependencias sobre la API de <audio>
    |--------------------------------------------------------------------------
    | Livewire ejecuta este bloque una sola vez. Como el layout marca el
    | componente como persistente, el estado sobrevive a la navegación SPA.
    |
    | El backend solo interviene para resolver URLs:
    |     await $wire.resolveStream(videoId, fresh)  →  { ok, stream: { sources, expires_at }, is_favorite }
    |
    | Hay dos elementos <audio> ("platos"). Uno suena; en el otro se deja
    | cargada la canción siguiente, así el salto es inmediato y el modo DJ
    | puede fundir el final de una con el principio de la otra.
    */

    const MAX_RETRIES = 2;        // URLs nuevas que se piden por canción antes de saltarla
    const MAX_SKIPPED = 5;        // canciones seguidas sin stream antes de detenerse
    const STALL_TIMEOUT = 12000;  // ms atascado en "cargando" antes de pedir otra URL
    const EXPIRY_MARGIN = 30000;  // ms antes de la caducidad en que un enlace se da por muerto
    const RATES = [0.5, 0.75, 1, 1.25, 1.5, 2]; // velocidades de reproducción disponibles
    const SKIP_DEBOUNCE = 180;    // ms: de varios saltos más seguidos que esto solo se resuelve el último
    const PRELOAD_AFTER = 4;      // s sonando antes de dejar preparada la canción siguiente
    const HOVER_INTENT = 220;     // ms con el puntero sobre una canción antes de resolver su enlace
    const WARM_INTERVAL = 400;    // ms mínimos entre dos resoluciones anticipadas
    const CROSSFADE = 7;          // s de mezcla entre dos canciones en modo DJ
    const DJ_REFILL_AT = 2;       // con menos canciones que estas por delante, el modo DJ pide más
    const STREAM_CACHE = 60;      // enlaces resueltos que se recuerdan como máximo
    const ARTIST_URL = @js(route('artist', absolute: false));

    const root = $wire.$el;
    const $ = (name) => root.querySelector(`[data-el="${name}"]`);
    const decks = [$('audio'), $('audio-b')];
    const gains = new Map(decks.map((deck) => [deck, 1])); // ganancia de mezcla de cada plato
    let audio = decks[0];         // plato activo: el que suena y el que sigue la interfaz
    const idleDeck = () => decks.find((deck) => deck !== audio);

    const ui = Object.fromEntries(
        [
            'seek', 'current', 'duration', 'cover', 'cover-trigger', 'title', 'artist', 'favorite', 'offline-btn',
            'prev', 'toggle', 'next', 'shuffle', 'dj', 'mute', 'volume', 'toast', 'highlight',
            'queue-panel', 'queue-subtitle', 'queue-clear', 'queue-close', 'queue-list',
            'queue-toggle', 'queue-toggle-desktop', 'queue-count', 'queue-count-mobile',
            'cover-modal', 'cover-backdrop', 'cover-close', 'cover-modal-img',
            'cover-modal-title', 'cover-modal-artist', 'cover-modal-enqueue', 'cover-modal-play',
            'cover-modal-offline', 'cover-modal-offline-label', 'cover-modal-download',
            'speed-panel',
        ].map((name) => [name, $(name)]),
    );
    const speedToggles = [...root.querySelectorAll('[data-speed-toggle]')];
    const speedLabels = [...root.querySelectorAll('[data-speed-label]')];
    const speedOptions = [...root.querySelectorAll('[data-speed]')];

    const state = {
        queue: [],            // canciones [{ video_id, title, artist, thumbnail_url, duration }]
        index: -1,
        stream: null,         // última respuesta del backend para la canción actual
        source: null,         // fuente activa dentro de stream.sources
        failedUrls: new Set(),
        token: 0,             // invalida cargas viejas cuando se salta rápido
        lastLoadAt: 0,        // cuándo empezó la última carga (para detectar saltos seguidos)
        wantsToPlay: false,   // intención del usuario (independiente de si ya suena)
        resolving: false,     // esperando la URL del backend
        buffering: false,
        seeking: false,
        position: 0,          // último segundo reproducido: desde ahí se reanuda al renovar la URL
        resumeAt: 0,
        retries: 0,
        failures: 0,
        volume: 0.8,          // volumen elegido (el de cada plato es este por su ganancia de mezcla)
        muted: false,
        rate: 1,              // velocidad de reproducción elegida
        shuffle: false,       // modo aleatorio
        bag: [],              // aleatorio: canciones que faltan por sonar en esta vuelta, ya barajadas
        played: [],           // canciones por las que se ha pasado, para que "anterior" vuelva por ellas
        dj: false,            // modo DJ: mezcla entre canciones y fila que no se acaba
        refilledFor: null,    // canción con la que el modo DJ ya pidió más música
        refilling: false,
        prepared: null,       // canción siguiente lista en el otro plato { track, deck, source, result }
        fade: null,           // mezcla en curso { outgoing, incoming, timer }
        recordPending: false, // la canción actual aún no se ha anotado en el historial
        glow: null,
        stallTimer: null,
        toastTimer: null,
        modalTrack: null,     // canción cuya portada se muestra en el modal
    };

    const current = () => state.queue[state.index] ?? null;
    const isCurrent = (track) => Boolean(track && current()?.video_id === track.video_id);

    /* ---------- Utilidades ---------- */

    function store(key, value) {
        try {
            if (value === undefined) return localStorage.getItem(`player:${key}`);
            localStorage.setItem(`player:${key}`, value);
        } catch {
            return null; // modo privado o almacenamiento bloqueado
        }
    }

    function formatTime(seconds) {
        if (!Number.isFinite(seconds) || seconds < 0) return '0:00';
        const total = Math.floor(seconds);
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = String(total % 60).padStart(2, '0');
        return h ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
    }

    // Las portadas de googleusercontent aceptan el tamaño en la propia URL.
    const sized = (url, size) => (url || '').replace(/=w\d+-h\d+/, `=w${size}-h${size}`);

    function highResCover(track) {
        const raw = track?.thumbnail_url || '';
        if (/=w\d+-h\d+/.test(raw)) {
            return sized(raw, 1080);
        }
        if (track?.video_id && /^[\w-]{11}$/.test(track.video_id) && raw.includes('ytimg.com')) {
            return `https://i.ytimg.com/vi/${track.video_id}/maxresdefault.jpg`;
        }
        return raw || (track?.video_id ? `https://i.ytimg.com/vi/${track.video_id}/hqdefault.jpg` : '');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function navigateToArtist(artistName) {
        const clean = (artistName || '').trim();
        if (!clean || clean === 'Busca una canción para empezar') return;

        const targetUrl = `${ARTIST_URL}?name=${encodeURIComponent(clean)}`;
        if (window.Livewire?.navigate) {
            window.Livewire.navigate(targetUrl);
        } else {
            window.location.href = targetUrl;
        }
    }

    const detail = (payload) => (Array.isArray(payload) ? payload[0] : payload) ?? {};
    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    function shuffled(list) {
        const copy = [...list];
        for (let i = copy.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [copy[i], copy[j]] = [copy[j], copy[i]];
        }
        return copy;
    }

    /* ---------- Enlaces de stream (con memoria y resolución anticipada) ---------- */

    // video_id → { result } ya resuelto o { promise } en camino. Pedir dos veces
    // la misma canción no hace dos viajes al servidor.
    const streams = new Map();
    let lastWarmAt = 0;

    function resolveStream(videoId, fresh = false) {
        const known = streams.get(videoId);

        if (!fresh && known?.promise) return known.promise;
        if (!fresh && known?.result && known.result.stream.expires_at * 1000 - Date.now() > EXPIRY_MARGIN) {
            return Promise.resolve(known.result);
        }

        const promise = $wire
            .resolveStream(videoId, fresh)
            .catch(() => null)
            .then((result) => {
                if (streams.get(videoId)?.promise === promise) {
                    result?.ok ? streams.set(videoId, { result }) : streams.delete(videoId);
                }
                return result;
            });

        streams.delete(videoId); // lo reinserta al final: el Map conserva el orden de uso
        streams.set(videoId, { promise });
        for (const key of streams.keys()) {
            if (streams.size <= STREAM_CACHE) break;
            streams.delete(key);
        }

        return promise;
    }

    // Resuelve el enlace de una canción antes de que se pulse (al acercar el
    // puntero o al empezar el clic): cuando se pulsa de verdad, ya está.
    function warm(track) {
        const id = track?.video_id;
        if (!id || streams.has(id) || offlineIds.has(id) || isCurrent(track)) return;
        if (performance.now() - lastWarmAt < WARM_INTERVAL) return;

        lastWarmAt = performance.now();
        resolveStream(id);
    }

    /* ---------- Almacenamiento Sin Internet (IndexedDB en la app) ---------- */

    const OFFLINE_DB_NAME = 'onda-offline-v1';
    const OFFLINE_STORE = 'tracks';
    const offlineIds = new Set();
    const activeOfflineDownloads = new Set();
    let currentBlobUrl = null;

    function openOfflineDb() {
        return new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) {
                return reject(new Error('IndexedDB no soportado'));
            }
            const req = indexedDB.open(OFFLINE_DB_NAME, 1);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(OFFLINE_STORE)) {
                    db.createObjectStore(OFFLINE_STORE, { keyPath: 'video_id' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function getOfflineTrack(videoId) {
        if (!videoId) return null;
        try {
            const db = await openOfflineDb();
            return await new Promise((resolve) => {
                const tx = db.transaction(OFFLINE_STORE, 'readonly');
                const req = tx.objectStore(OFFLINE_STORE).get(videoId);
                req.onsuccess = () => resolve(req.result || null);
                req.onerror = () => resolve(null);
            });
        } catch {
            return null;
        }
    }

    async function syncOfflineBadges() {
        try {
            const db = await openOfflineDb();
            const keys = await new Promise((resolve) => {
                const tx = db.transaction(OFFLINE_STORE, 'readonly');
                const req = tx.objectStore(OFFLINE_STORE).getAllKeys();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => resolve([]);
            });
            offlineIds.clear();
            keys.forEach((k) => offlineIds.add(String(k)));
        } catch {
            // IndexedDB bloqueado o no disponible
        }

        document.querySelectorAll('[data-video-id]').forEach((row) => {
            const id = row.getAttribute('data-video-id');
            const isSaved = offlineIds.has(id);
            row.setAttribute('data-offline', isSaved ? 'true' : 'false');
            const label = row.querySelector('.offline-menu-label');
            if (label) {
                label.textContent = isSaved
                    ? 'Quitar de canciones sin internet'
                    : 'Guardar sin internet (en la app)';
            }
        });

        const cur = current();
        if (ui['offline-btn']) {
            const isCurSaved = Boolean(cur && offlineIds.has(cur.video_id));
            ui['offline-btn'].dataset.saved = String(isCurSaved);
            ui['offline-btn'].title = isCurSaved
                ? 'Guardada sin internet en la app (clic para quitar)'
                : 'Guardar sin internet en la app';
        }

        if (state.modalTrack && ui['cover-modal-offline-label']) {
            const isModalSaved = offlineIds.has(state.modalTrack.video_id);
            ui['cover-modal-offline-label'].textContent = isModalSaved ? 'En la app ✓' : 'Offline';
        }
    }

    async function removeTrackOffline(videoId, { notify = true } = {}) {
        if (!videoId) return;
        try {
            const db = await openOfflineDb();
            await new Promise((resolve) => {
                const tx = db.transaction(OFFLINE_STORE, 'readwrite');
                tx.objectStore(OFFLINE_STORE).delete(videoId);
                tx.oncomplete = () => resolve(true);
                tx.onerror = () => resolve(false);
            });
        } catch {
            // ignorar
        }

        offlineIds.delete(videoId);
        await syncOfflineBadges();

        try {
            await $wire.markOfflineRemoved(videoId);
            Livewire.dispatch('offline-changed');
        } catch {
            // puede estar sin conexión
        }

        if (notify) {
            toast('Canción eliminada del almacenamiento sin internet');
        }
    }

    async function toggleTrackOffline(track) {
        if (!track?.video_id) return;

        if (offlineIds.has(track.video_id)) {
            return removeTrackOffline(track.video_id);
        }

        if (activeOfflineDownloads.has(track.video_id)) {
            toast(`Ya se está guardando «${track.title}»…`);
            return;
        }

        activeOfflineDownloads.add(track.video_id);
        toast(`Guardando «${track.title}» para escuchar sin internet…`);

        try {
            const result = await $wire.resolveStream(track.video_id, false);
            if (!result?.ok || !result.stream?.sources?.length) {
                throw new Error('No se pudo resolver el audio');
            }

            const source = pickSource(result.stream.sources) || result.stream.sources[0];
            const streamUrl = source.url;
            const mimeType = source.mime_type || 'audio/webm';

            // Descarga por bloques de 2 MB desde el proxy local /stream/{videoId}/0
            const chunks = [];
            const chunkSize = 2 * 1024 * 1024;
            let start = 0;
            let totalSize = 0;
            const maxBytes = 40 * 1024 * 1024; // límite de seguridad de 40 MB por canción

            while (start < maxBytes) {
                const end = start + chunkSize - 1;
                const response = await fetch(streamUrl, {
                    headers: { Range: `bytes=${start}-${end}` },
                    credentials: 'same-origin',
                });

                if (!response.ok && response.status !== 206) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const buffer = await response.arrayBuffer();
                if (buffer.byteLength === 0) break;

                chunks.push(buffer);
                start += buffer.byteLength;

                const contentRange = response.headers.get('Content-Range');
                if (contentRange) {
                    const match = contentRange.match(/\/(\d+)$/);
                    if (match) totalSize = parseInt(match[1], 10);
                }

                // Si el servidor devolvió el archivo completo (200) o llegamos al final:
                if (response.status === 200 || (totalSize > 0 && start >= totalSize) || buffer.byteLength < 64 * 1024) {
                    break;
                }
            }

            if (!chunks.length) {
                throw new Error('Audio vacío');
            }

            const blob = new Blob(chunks, { type: mimeType });
            const record = {
                video_id: track.video_id,
                title: track.title || 'Canción',
                artist: track.artist || '',
                thumbnail_url: track.thumbnail_url || '',
                duration: Number(track.duration || 0),
                mime_type: mimeType,
                size_bytes: blob.size,
                blob,
                saved_at: Date.now(),
            };

            const db = await openOfflineDb();
            await new Promise((resolve, reject) => {
                const tx = db.transaction(OFFLINE_STORE, 'readwrite');
                tx.objectStore(OFFLINE_STORE).put(record);
                tx.oncomplete = () => resolve(true);
                tx.onerror = () => reject(tx.error);
            });

            offlineIds.add(track.video_id);
            await syncOfflineBadges();

            try {
                await $wire.markOfflineSaved(
                    {
                        video_id: record.video_id,
                        title: record.title,
                        artist: record.artist,
                        thumbnail_url: record.thumbnail_url,
                        duration: record.duration,
                    },
                    blob.size,
                    mimeType,
                );
                Livewire.dispatch('offline-changed');
            } catch {
                // metadatos opcionales
            }

            const mb = (blob.size / (1024 * 1024)).toFixed(1);
            toast(`«${track.title}» guardada sin internet en la app (${mb} MB)`);
        } catch {
            toast(`No se pudo guardar «${track.title}» sin internet. Inténtalo de nuevo.`);
        } finally {
            activeOfflineDownloads.delete(track.video_id);
        }
    }

    /* ---------- Reproducción y fila de reproducción ---------- */

    function playQueue(tracks, index = 0) {
        state.queue = tracks;
        state.failures = 0;
        state.bag = [];
        state.played = [];
        state.refilledFor = null;
        state.wantsToPlay = true;
        load(index, { viaHistory: true });
        renderQueue();
    }

    function enqueueTrack(track) {
        if (!track?.video_id) return;

        const wasEmpty = state.queue.length === 0;
        state.queue.push(track);
        if (state.shuffle) state.bag.push(track);

        if (wasEmpty) {
            state.failures = 0;
            state.wantsToPlay = true;
            load(0);
        } else {
            renderQueue();
        }

        toast(`«${track.title}» añadida a la fila de reproducción`);
    }

    function enqueueTracks(tracks) {
        if (!Array.isArray(tracks) || !tracks.length) return;

        const wasEmpty = state.queue.length === 0;
        state.queue.push(...tracks);
        if (state.shuffle) state.bag.push(...shuffled(tracks));

        if (wasEmpty) {
            state.failures = 0;
            state.wantsToPlay = true;
            load(0);
        } else {
            renderQueue();
        }

        toast(`${tracks.length} canciones añadidas a la fila de reproducción`);
    }

    function removeFromQueue(removeIndex) {
        if (removeIndex < 0 || removeIndex >= state.queue.length) return;

        const removingCurrent = removeIndex === state.index;
        state.queue.splice(removeIndex, 1);

        if (state.queue.length === 0) {
            clearQueue();
            return;
        }

        if (removeIndex < state.index) {
            state.index--;
            renderQueue();
        } else if (removingCurrent) {
            const nextIdx = state.index % state.queue.length;
            state.index = -1; // la que sonaba ya no está: no entra en el historial
            load(nextIdx);
        } else {
            renderQueue();
        }
    }

    function clearQueue() {
        state.token++;
        clearTimeout(state.stallTimer);
        stopFade();
        cancelPrepared();
        decks.forEach(releaseDeck);
        state.queue = [];
        state.index = -1;
        state.bag = [];
        state.played = [];
        state.refilledFor = null;
        state.stream = null;
        state.source = null;
        state.wantsToPlay = false;
        state.resolving = false;
        state.buffering = false;
        state.position = 0;
        ui.title.textContent = 'Nada sonando';
        ui.artist.textContent = 'Busca una canción para empezar';
        ui.duration.textContent = '0:00';
        ui.seek.disabled = true;
        ui.cover.classList.add('opacity-0');
        renderProgress(0);
        setFavorite(false);
        clearGlow();
        refreshStatus();
        highlight();
        renderQueue();
    }

    function toggleQueuePanel(forceOpen) {
        const isOpen = ui['queue-panel'].dataset.open === 'true';
        const nextOpen = typeof forceOpen === 'boolean' ? forceOpen : !isOpen;

        ui['queue-panel'].dataset.open = String(nextOpen);
        ui['queue-toggle']?.setAttribute('data-open', String(nextOpen));
        ui['queue-toggle-desktop']?.setAttribute('data-open', String(nextOpen));

        if (nextOpen) {
            renderQueue();
            toggleSpeedPanel(false);
        }
    }

    /* ---------- Velocidad, volumen y modos ---------- */

    function setRate(rate) {
        state.rate = RATES.includes(rate) ? rate : 1;

        // defaultPlaybackRate es la que el navegador aplica al cargar la canción siguiente.
        for (const deck of decks) {
            deck.defaultPlaybackRate = state.rate;
            deck.playbackRate = state.rate;
            deck.preservesPitch = true;
        }
        store('rate', state.rate);

        speedLabels.forEach((label) => (label.textContent = `${state.rate}×`));
        speedToggles.forEach((button) => (button.dataset.active = String(state.rate !== 1)));
        speedOptions.forEach((option) => {
            option.setAttribute('aria-checked', String(Number(option.dataset.speed) === state.rate));
        });
    }

    function toggleSpeedPanel(forceOpen) {
        const isOpen = ui['speed-panel'].dataset.open === 'true';
        const nextOpen = typeof forceOpen === 'boolean' ? forceOpen : !isOpen;

        ui['speed-panel'].dataset.open = String(nextOpen);
        speedToggles.forEach((button) => {
            button.dataset.open = String(nextOpen);
            button.setAttribute('aria-expanded', String(nextOpen));
        });

        if (nextOpen) {
            toggleQueuePanel(false);
        }
    }

    // El volumen real de cada plato es el elegido por su ganancia de mezcla (1 salvo durante un fundido).
    function applyVolume() {
        for (const deck of decks) {
            deck.volume = Math.min(1, Math.max(0, state.volume * gains.get(deck)));
            deck.muted = state.muted;
        }
    }

    function setGain(deck, gain) {
        gains.set(deck, gain);
        deck.volume = Math.min(1, Math.max(0, state.volume * gain));
    }

    function setShuffle(on, { silent = false } = {}) {
        state.shuffle = Boolean(on);
        state.bag = []; // se baraja de nuevo a partir de la canción que suena
        store('shuffle', state.shuffle ? '1' : '0');

        ui.shuffle.dataset.active = String(state.shuffle);
        ui.shuffle.setAttribute('aria-pressed', String(state.shuffle));
        ui.shuffle.title = state.shuffle ? 'Aleatorio activado' : 'Aleatorio';

        cancelPrepared(); // la canción siguiente ya no es la misma
        if (!silent) toast(state.shuffle ? 'Aleatorio activado' : 'Aleatorio desactivado');
    }

    function setDj(on, { silent = false } = {}) {
        state.dj = Boolean(on);
        state.refilledFor = null;
        store('dj', state.dj ? '1' : '0');

        ui.dj.dataset.active = String(state.dj);
        ui.dj.setAttribute('aria-pressed', String(state.dj));

        if (state.dj) refillDj();
        if (!silent) {
            toast(state.dj
                ? 'Modo DJ activado: mezcla las canciones y mantiene la fila con música parecida'
                : 'Modo DJ desactivado');
        }
    }

    // Modo DJ: cuando quedan pocas canciones por delante, pide al servidor una
    // tanda que pegue con la que suena y la añade al final de la fila.
    async function refillDj() {
        const track = current();
        if (!state.dj || state.refilling || !track || state.refilledFor === track.video_id) return;

        const ahead = state.shuffle ? pendingBag().length : state.queue.length - 1 - state.index;
        if (ahead >= DJ_REFILL_AT) return;

        state.refilling = true;
        state.refilledFor = track.video_id;

        try {
            const { video_id, title, artist, thumbnail_url } = track;
            const more = await $wire.djMix({ video_id, title, artist, thumbnail_url }, state.queue.map((item) => item.video_id));
            const queued = new Set(state.queue.map((item) => item.video_id));
            const fresh = (Array.isArray(more) ? more : []).filter((item) => item?.video_id && !queued.has(item.video_id));

            if (state.dj && fresh.length) {
                state.queue.push(...fresh);
                if (state.shuffle) state.bag.push(...fresh);
                renderQueue();
                toast(`Modo DJ: ${fresh.length} canciones más en la fila`);
            }
        } catch {
            // sin conexión o servidor ocupado: se reintenta con la canción siguiente
        } finally {
            state.refilling = false;
        }
    }

    function renderQueue() {
        const count = state.queue.length;
        if (ui['queue-count']) ui['queue-count'].textContent = String(count);
        if (ui['queue-count-mobile']) ui['queue-count-mobile'].textContent = String(count);

        if (!ui['queue-list'] || !ui['queue-subtitle']) return;

        if (count === 0) {
            ui['queue-subtitle'].textContent = 'La fila está vacía';
            ui['queue-list'].innerHTML = `
                <li class="queue-empty">
                    No hay canciones en la fila. Pulsa una canción para reproducirla o usa «Añadir a la fila» para ir armando tu lista.
                </li>
            `;
            return;
        }

        const modes = [state.shuffle ? 'aleatorio' : '', state.dj ? 'modo DJ' : ''].filter(Boolean).join(' · ');
        ui['queue-subtitle'].textContent = `${count} ${count === 1 ? 'canción' : 'canciones'}${modes ? ` · ${modes}` : ''}`;

        ui['queue-list'].innerHTML = state.queue
            .map((track, idx) => {
                const active = idx === state.index;
                const thumb = escapeHtml(sized(track.thumbnail_url, 120));
                const title = escapeHtml(track.title);
                const artist = escapeHtml(track.artist || '');
                const dur = track.duration > 0 ? formatTime(track.duration) : '';

                return `
                    <li class="queue-item ${active ? 'is-active' : ''}" data-queue-index="${idx}">
                        <button type="button" data-queue-zoom="${idx}" class="cover-zoom-btn relative size-10 shrink-0 overflow-hidden rounded-lg bg-ink/10" title="Ver portada en grande">
                            <img src="${thumb}" alt="" loading="lazy" referrerpolicy="no-referrer" class="size-full object-cover">
                        </button>
                        <button type="button" data-queue-play="${idx}" class="min-w-0 flex-1 text-left">
                            <span class="queue-item-title block truncate text-xs font-semibold">${title}</span>
                            <span class="block truncate text-[11px] text-ink/60">${active ? 'Sonando ahora · ' : ''}${artist}</span>
                        </button>
                        ${dur ? `<span class="text-[11px] text-ink/45 tabular-nums">${dur}</span>` : ''}
                        <button type="button" data-queue-remove="${idx}" class="action-btn grid size-7 shrink-0 place-items-center rounded-full hover:text-danger" aria-label="Quitar de la fila">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </li>
                `;
            })
            .join('');
    }

    /* ---------- Modal de portada en grande ---------- */

    function openCoverModal(track) {
        const target = track || current();
        if (!target?.thumbnail_url && !target?.video_id) return;

        state.modalTrack = target;
        const fallbackSrc = sized(target.thumbnail_url, 544) || `https://i.ytimg.com/vi/${target.video_id}/hqdefault.jpg`;
        const hiResSrc = highResCover(target);

        ui['cover-modal-img'].onerror = () => {
            if (ui['cover-modal-img'].src !== fallbackSrc) {
                ui['cover-modal-img'].src = fallbackSrc;
            }
        };
        ui['cover-modal-img'].src = hiResSrc;
        ui['cover-modal-title'].textContent = target.title || 'Canción';
        ui['cover-modal-artist'].textContent = target.artist || '';

        if (ui['cover-modal-download']) {
            const params = new URLSearchParams({
                title: target.title || 'Canción',
                artist: target.artist || '',
            });
            ui['cover-modal-download'].href = `/download/${encodeURIComponent(target.video_id)}?${params.toString()}`;
        }

        if (ui['cover-modal-offline-label']) {
            ui['cover-modal-offline-label'].textContent = offlineIds.has(target.video_id) ? 'En la app ✓' : 'Offline';
        }

        ui['cover-modal'].dataset.open = 'true';
    }

    function closeCoverModal() {
        if (ui['cover-modal']) {
            ui['cover-modal'].dataset.open = 'false';
        }
    }

    /* ---------- Carga de una canción ---------- */

    // viaHistory: no anota la canción que se deja en el historial de "anterior"
    // (se usa al volver atrás y al empezar una lista nueva).
    async function load(index, { resumeAt = 0, fresh = false, viaHistory = false } = {}) {
        const track = state.queue[index];
        if (!track) return;

        const token = ++state.token;
        const leaving = current();
        clearTimeout(state.stallTimer);
        stopFade();

        if (!fresh) {
            if (leaving && leaving !== track && !viaHistory) rememberPlayed(leaving);
            state.bag = state.bag.filter((item) => item !== track);
        }

        state.index = index;
        state.buffering = false;
        state.resumeAt = resumeAt;

        // La canción ya estaba preparada en el otro plato: empieza a sonar al instante.
        if (!fresh && state.prepared?.track === track && state.prepared.deck) {
            return adoptPrepared(track);
        }

        state.resolving = true;

        if (!fresh) {
            cancelPrepared();
            state.retries = 0;
            state.position = 0;
            state.recordPending = true;
            audio.pause();
            showTrack(track);
        }
        refreshStatus();

        // Si se pulsa "siguiente" varias veces seguidas solo se resuelve la última;
        // un clic suelto no espera nada.
        const rapid = performance.now() - state.lastLoadAt < SKIP_DEBOUNCE;
        state.lastLoadAt = performance.now();
        if (rapid) {
            await wait(SKIP_DEBOUNCE);
            if (token !== state.token) return;
        }

        // 1. Primero comprobamos si la canción está guardada SIN INTERNET en IndexedDB.
        if (!fresh && offlineIds.has(track.video_id)) {
            const offlineRecord = await getOfflineTrack(track.video_id);
            if (token !== state.token) return;

            if (offlineRecord?.blob) {
                if (currentBlobUrl) {
                    URL.revokeObjectURL(currentBlobUrl);
                }
                currentBlobUrl = URL.createObjectURL(offlineRecord.blob);
                const offlineSource = {
                    url: currentBlobUrl,
                    mime_type: offlineRecord.mime_type || 'audio/webm',
                    codec: null,
                    bitrate: 160000,
                };
                state.resolving = false;
                state.stream = {
                    sources: [offlineSource],
                    duration: offlineRecord.duration || track.duration || 0,
                    expires_at: Math.floor(Date.now() / 1000) + 86400,
                };
                state.source = offlineSource;
                state.failedUrls = new Set();
                audio.src = currentBlobUrl;

                if (state.wantsToPlay) {
                    play();
                }
                refreshStatus();
                return;
            }
        }

        // 2. Si no, se pide el enlace (que puede estar ya resuelto por adelantado).
        const result = await resolveStream(track.video_id, fresh);
        if (token !== state.token) return;

        const failedUrls = new Set();
        const source = result?.ok ? pickSource(result.stream.sources, failedUrls) : null;
        state.resolving = false;

        if (!source) return giveUp();

        state.stream = result.stream;
        state.source = source;
        state.failedUrls = failedUrls;
        setFavorite(result.is_favorite);
        audio.src = source.url;

        if (state.wantsToPlay) {
            play();
            watchStall();
        }
        refreshStatus();
    }

    // Primera fuente (vienen ordenadas de mejor a peor) que este navegador sabe decodificar.
    function pickSource(sources = [], exclude = new Set()) {
        const candidates = sources.filter((source) => !exclude.has(source.url));
        const mime = (source) => (source.codec ? `${source.mime_type}; codecs="${source.codec}"` : source.mime_type);

        return (
            candidates.find((source) => audio.canPlayType(mime(source)) === 'probably') ??
            candidates.find((source) => audio.canPlayType(mime(source)) !== '') ??
            candidates[0] ??
            null
        );
    }

    function play() {
        audio.play().catch((error) => {
            // Autoplay bloqueado por el navegador: queda en pausa hasta el próximo clic.
            // (AbortError = llegó otra canción; los fallos de red los atiende el evento "error".)
            if (error.name === 'NotAllowedError') {
                state.wantsToPlay = false;
                refreshStatus();
            }
        });
    }

    function toggle() {
        if (!current()) return;

        if (state.wantsToPlay) {
            state.wantsToPlay = false;
            stopFade();
            audio.pause();
            return refreshStatus();
        }

        state.wantsToPlay = true;

        // Tras una pausa larga el enlace puede haber caducado: se renueva antes de seguir.
        const expired = state.stream && state.stream.expires_at * 1000 - Date.now() < EXPIRY_MARGIN;
        if (!state.stream || expired || audio.error) {
            return load(state.index, { resumeAt: state.position, fresh: true });
        }

        play();
        refreshStatus();
    }

    /* ---------- Orden de reproducción ---------- */

    // Aleatorio: canciones que aún no han sonado en esta vuelta, ya barajadas.
    // Si la bolsa se vacía se vuelve a barajar toda la fila (menos la que suena).
    function pendingBag() {
        const playing = current();
        state.bag = state.bag.filter((track) => track !== playing && state.queue.includes(track));

        if (!state.bag.length) {
            state.bag = shuffled(state.queue.filter((track) => track !== playing));
        }

        return state.bag;
    }

    // Índice de la canción que toca después de la actual. No cambia nada: se
    // puede consultar las veces que haga falta y siempre responde lo mismo.
    function upcomingIndex() {
        if (!state.queue.length) return -1;

        if (state.shuffle) {
            const [upcoming] = pendingBag();
            return upcoming ? state.queue.indexOf(upcoming) : state.index;
        }

        return (state.index + 1) % state.queue.length; // la fila es circular
    }

    function rememberPlayed(track) {
        state.played.push(track);
        if (state.played.length > 200) state.played.shift();
    }

    function next() {
        if (!state.queue.length) return;
        state.wantsToPlay = true;
        load(upcomingIndex());
    }

    function prev() {
        if (!state.queue.length) return;
        if (audio.currentTime > 3) {
            audio.currentTime = 0;
            return;
        }
        state.wantsToPlay = true;

        // En aleatorio "anterior" deshace el camino: vuelve a la que sonó antes
        // y deja la que se abandona como la siguiente.
        if (state.shuffle) {
            const leaving = current();

            while (state.played.length) {
                const index = state.queue.indexOf(state.played.pop());

                if (index >= 0 && index !== state.index) {
                    if (leaving) state.bag.unshift(leaving);
                    return load(index, { viaHistory: true });
                }
            }
        }

        load((state.index - 1 + state.queue.length) % state.queue.length, { viaHistory: true });
    }

    /* ---------- Canción siguiente preparada en el otro plato ---------- */

    function releaseDeck(deck) {
        deck.pause();
        deck.removeAttribute('src');
        deck.load();
    }

    function cancelPrepared() {
        const prepared = state.prepared;
        state.prepared = null;
        if (prepared?.deck) releaseDeck(prepared.deck);
    }

    // Mientras suena una canción, deja la siguiente con el enlace resuelto y los
    // primeros segundos descargados en el plato libre.
    function ensurePrepared() {
        if (state.fade || state.resolving) return;

        const upcoming = state.queue[upcomingIndex()];
        if (!upcoming || upcoming === current() || state.prepared?.track === upcoming) return;

        prepareNext(upcoming);
    }

    async function prepareNext(track) {
        cancelPrepared();

        // Si no se puede preparar, la entrada se queda sin plato para no reintentarlo en bucle.
        const ticket = (state.prepared = { track, deck: null, source: null, result: null });
        if (offlineIds.has(track.video_id)) return; // las guardadas ya cargan al instante

        const result = await resolveStream(track.video_id);
        if (state.prepared !== ticket || state.fade) return;

        const source = result?.ok ? pickSource(result.stream.sources) : null;
        if (!source) return;

        const deck = idleDeck();
        Object.assign(ticket, { deck, source, result });
        setGain(deck, 1);
        deck.preload = 'auto';
        deck.src = source.url;
        deck.load();
    }

    // Lo que hay que anotar cuando el plato preparado pasa a ser el activo.
    function takeOver(track, { result, source, deck }) {
        audio = deck;
        state.prepared = null;
        state.index = state.queue.indexOf(track);
        state.resolving = false;
        state.buffering = false;
        state.retries = 0;
        state.position = 0;
        state.resumeAt = 0;
        state.recordPending = true;
        state.stream = result.stream;
        state.source = source;
        state.failedUrls = new Set();

        showTrack(track);
        setFavorite(result.is_favorite);
        if (audio.readyState >= HTMLMediaElement.HAVE_METADATA) onMetadata();
    }

    // Cambio seco a la canción preparada (botón "siguiente" o final de canción sin modo DJ).
    function adoptPrepared(track) {
        const outgoing = audio;

        takeOver(track, state.prepared);
        releaseDeck(outgoing);
        applyVolume();

        if (state.wantsToPlay) {
            play();
            watchStall();
        }
        refreshStatus();
    }

    /* ---------- Modo DJ: mezcla entre canciones ---------- */

    // Cuando a la canción le quedan unos segundos, la siguiente (ya preparada)
    // empieza a sonar por debajo y las dos se cruzan.
    function maybeCrossfade() {
        if (!state.dj || state.fade || !state.wantsToPlay || audio.paused || state.seeking) return;

        const prepared = state.prepared;
        const remaining = (audio.duration - audio.currentTime) / (audio.playbackRate || 1);
        const tooShort = !Number.isFinite(audio.duration) || audio.duration < CROSSFADE * 3;

        if (tooShort || remaining > CROSSFADE || remaining < 0.5) return;
        if (!prepared?.deck || prepared.deck.readyState < HTMLMediaElement.HAVE_FUTURE_DATA) return;
        if (prepared.track !== state.queue[upcomingIndex()]) return;

        crossfade(prepared, remaining);
    }

    function crossfade(prepared, seconds) {
        const outgoing = audio;
        const incoming = prepared.deck;
        const leaving = current();

        state.token++; // invalida cualquier carga que estuviera en curso
        clearTimeout(state.stallTimer);
        if (leaving) rememberPlayed(leaving);
        state.bag = state.bag.filter((item) => item !== prepared.track);

        setGain(incoming, 0);
        takeOver(prepared.track, prepared); // desde aquí la interfaz sigue a la canción que entra

        const startedAt = performance.now();
        const duration = Math.max(1, seconds) * 1000;

        // Curva de igual potencia: el volumen del conjunto no cae a mitad de la mezcla.
        const step = () => {
            const progress = Math.min(1, (performance.now() - startedAt) / duration);
            setGain(incoming, Math.sin((progress * Math.PI) / 2));
            setGain(outgoing, Math.cos((progress * Math.PI) / 2));
            if (progress >= 1 || outgoing.ended) stopFade();
        };

        state.fade = { outgoing, incoming, timer: setInterval(step, 60) };
        step();
        play();
        refreshStatus();
    }

    // Termina la mezcla en curso: la canción que salía se corta y la que entra queda a volumen normal.
    function stopFade() {
        const fade = state.fade;
        if (!fade) return;

        clearInterval(fade.timer);
        state.fade = null;
        releaseDeck(fade.outgoing);
        setGain(fade.outgoing, 1);
        setGain(fade.incoming, 1);
    }

    /* ---------- Recuperación de errores ---------- */

    // El stream falló o caducó: prueba otra fuente alternativa o pide otra URL y sigue donde estaba.
    function recover() {
        if (!current()) return;

        if (state.source?.url) {
            state.failedUrls.add(state.source.url);
        }

        const fallback = pickSource(state.stream?.sources ?? [], state.failedUrls);
        if (fallback) {
            state.source = fallback;
            state.resumeAt = state.position;
            audio.src = fallback.url;
            if (state.wantsToPlay) {
                play();
                watchStall();
            }
            return refreshStatus();
        }

        if (state.retries >= MAX_RETRIES) return giveUp();

        state.retries++;
        load(state.index, { resumeAt: state.position, fresh: true });
    }

    function giveUp() {
        const track = current();
        state.stream = null;
        state.failures++;
        streams.delete(track.video_id);

        if (state.failures < Math.min(state.queue.length, MAX_SKIPPED)) {
            toast(`«${track.title}» no está disponible ahora. Pasando a la siguiente…`);
            return next();
        }

        state.wantsToPlay = false;
        refreshStatus();
        toast('No hay stream disponible ahora mismo. Vuelve a intentarlo en un momento.');
    }

    function watchStall() {
        clearTimeout(state.stallTimer);
        state.stallTimer = setTimeout(() => {
            const stuck = audio.readyState < HTMLMediaElement.HAVE_FUTURE_DATA;
            if (state.wantsToPlay && !state.resolving && stuck) recover();
        }, STALL_TIMEOUT);
    }

    /* ---------- Eventos de <audio> ---------- */

    // Los dos platos comparten manejadores, pero solo se atiende al activo: el
    // otro está precargando la siguiente canción o apagándose en una mezcla.
    function onAudio(type, handler) {
        for (const deck of decks) {
            deck.addEventListener(type, (event) => {
                if (event.target === audio) handler(event);
            });
        }
    }

    onAudio('error', () => {
        if (!state.resolving && audio.error) recover();
    });

    onAudio('waiting', () => {
        state.buffering = true;
        watchStall();
        refreshStatus();
    });

    onAudio('canplay', () => {
        state.buffering = false;
        clearTimeout(state.stallTimer);
        refreshStatus();
    });

    onAudio('playing', () => {
        state.buffering = false;
        state.retries = 0;
        state.failures = 0;
        clearTimeout(state.stallTimer);
        refreshStatus();
        recordPlay();
        refillDj();
    });

    // Anota la canción en "Escuchadas recientemente" y en el resumen mensual una sola vez por carga.
    function recordPlay() {
        const track = current();
        if (!state.recordPending || !track) return;
        state.recordPending = false;

        const { video_id, title, artist, thumbnail_url } = track;
        const duration = Math.round(audio.duration || track.duration || 210);
        $wire
            .recordPlay({ video_id, title, artist, thumbnail_url, duration })
            .then(() => Livewire.dispatch('play-recorded'))
            .catch(() => {}); // el historial es accesorio: si falla, la música sigue
    }

    onAudio('pause', () => {
        // Pausa externa (auriculares, sistema…): respeta la intención de parar.
        if (!state.resolving && !audio.ended) {
            state.wantsToPlay = false;
            stopFade();
        }
        refreshStatus();
    });

    onAudio('ended', next);

    function onMetadata() {
        if (state.resumeAt > 0) {
            audio.currentTime = Math.min(state.resumeAt, Math.max(0, audio.duration - 1));
        }
        state.resumeAt = 0;
        audio.playbackRate = state.rate;
        ui.seek.disabled = false;
        ui.duration.textContent = formatTime(audio.duration);
    }

    onAudio('loadedmetadata', onMetadata);

    onAudio('timeupdate', () => {
        // Al cambiar o perder la fuente el navegador emite un timeupdate con 0:
        // no debe pisar la posición desde la que hay que reanudar.
        if (state.resolving || state.resumeAt > 0 || audio.readyState === HTMLMediaElement.HAVE_NOTHING) return;
        state.position = audio.currentTime;
        if (!state.seeking) renderProgress(audio.currentTime);

        if (audio.currentTime >= PRELOAD_AFTER) ensurePrepared();
        maybeCrossfade();
    });

    /* ---------- Interfaz ---------- */

    function refreshStatus() {
        const status = !current()
            ? 'idle'
            : state.resolving || state.buffering || (state.wantsToPlay && audio.paused)
              ? 'loading'
              : audio.paused
                ? 'paused'
                : 'playing';

        if (root.dataset.status !== status) {
            root.dataset.status = status;
            highlight();
        }
        ui.toggle.setAttribute('aria-label', state.wantsToPlay ? 'Pausar' : 'Reproducir');
    }

    function showTrack(track) {
        ui.title.textContent = track.title;
        ui.artist.textContent = track.artist;
        ui.duration.textContent = formatTime(track.duration);
        ui.seek.disabled = true;
        renderProgress(0);
        setFavorite(false);

        ui.cover.classList.add('opacity-0');
        ui.cover.onload = () => ui.cover.classList.remove('opacity-0');
        ui.cover.src = sized(track.thumbnail_url, 226);

        updateGlow(track);
        highlight();
        renderQueue();
        syncOfflineBadges();

        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: track.title,
                artist: track.artist,
                artwork: track.thumbnail_url ? [{ src: sized(track.thumbnail_url, 544) }] : [],
            });
        }
    }

    function renderProgress(time) {
        const duration = audio.duration || current()?.duration || 0;
        const ratio = duration > 0 && Number.isFinite(duration) ? Math.min(1, time / duration) : 0;

        ui.current.textContent = formatTime(time);
        ui.seek.style.setProperty('--progress', `${ratio * 100}%`);
        if (!state.seeking) ui.seek.value = Math.round(ratio * 1000);
    }

    function renderVolume() {
        const level = state.muted ? 0 : state.volume;

        ui.volume.value = Math.round(level * 100);
        ui.volume.style.setProperty('--progress', `${level * 100}%`);
        ui.mute.setAttribute('aria-label', level === 0 ? 'Activar sonido' : 'Silenciar');
        root.dataset.muted = String(level === 0);
    }

    function setFavorite(active) {
        ui.favorite.dataset.active = String(Boolean(active));
        ui.favorite.setAttribute('aria-pressed', String(Boolean(active)));
    }

    function toast(message) {
        ui.toast.textContent = message;
        ui.toast.dataset.show = 'true';
        clearTimeout(state.toastTimer);
        state.toastTimer = setTimeout(() => (ui.toast.dataset.show = 'false'), 3500);
    }

    // Resalta la fila que suena en cualquier lista. Se hace con una regla CSS
    // (y no tocando las filas) para que sobreviva a los re-render de Livewire.
    // Los colores salen de las variables del tema, así que vale para claro y oscuro.
    function highlight() {
        const id = current()?.video_id;
        if (!id || !/^[\w-]{11}$/.test(id)) {
            ui.highlight.textContent = '';
            return;
        }

        const row = `[data-video-id="${id}"]`;
        const running = root.dataset.status === 'playing' ? `${row} .track-eq i { animation-play-state: running; }` : '';

        ui.highlight.textContent = `
            ${row} { background: color-mix(in srgb, var(--glow-1) 18%, transparent); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--glow-1) 30%, transparent); }
            ${row} .track-title, ${row} .track-eq { color: color-mix(in srgb, var(--glow-1) 42%, var(--color-ink)); font-weight: 600; }
            ${row} .track-index { display: none; }
            ${row} .track-eq { display: flex; }
            ${running}
        `;
    }

    /* ---------- Iluminación ambiental ---------- */

    const isDark = () => document.documentElement.dataset.scheme !== 'light';

    // Lee los píxeles de la portada y deriva dos colores dominantes.
    function updateGlow(track) {
        const apply = (palette) => isCurrent(track) && applyGlow(palette);
        const fallback = () => apply(fallbackPalette(track.video_id));

        if (!track.thumbnail_url) return fallback();

        const image = new Image();
        image.crossOrigin = 'anonymous'; // necesario para leer píxeles de otro dominio
        image.referrerPolicy = 'no-referrer';
        image.onerror = fallback;
        image.onload = () => {
            try {
                apply(extractPalette(image));
            } catch {
                fallback(); // el host no envía CORS: el canvas queda "contaminado"
            }
        };
        image.src = sized(track.thumbnail_url, 96);
    }

    function extractPalette(image) {
        const size = 24;
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = size;

        const context = canvas.getContext('2d', { willReadFrequently: true });
        context.drawImage(image, 0, 0, size, size);
        const { data } = context.getImageData(0, 0, size, size);

        // Agrupa los píxeles en 12 familias de tono y puntúa cuánto color aporta cada una.
        const families = new Map();
        for (let i = 0; i < data.length; i += 4) {
            const [h, s, l] = toHsl(data[i], data[i + 1], data[i + 2]);
            if (s < 0.15 || l < 0.1 || l > 0.92) continue; // grises, negros y blancos no tiñen

            const key = Math.round(h / 30) % 12;
            const family = families.get(key) ?? { key, r: 0, g: 0, b: 0, count: 0, score: 0 };
            family.r += data[i];
            family.g += data[i + 1];
            family.b += data[i + 2];
            family.count++;
            family.score += s * (1 - Math.abs(l - 0.5));
            families.set(key, family);
        }

        const ranked = [...families.values()].sort((a, b) => b.score - a.score);
        if (!ranked.length) {
            return neutralPalette(); // portada en blanco y negro
        }

        const average = (family) => toHsl(family.r / family.count, family.g / family.count, family.b / family.count);
        const distance = (a, b) => Math.min(Math.abs(a - b), 12 - Math.abs(a - b));

        const first = ranked[0];
        const second = ranked.find((family) => distance(family.key, first.key) >= 2 && family.score > first.score * 0.2);

        const [h1, s1] = average(first);
        const [h2, s2] = second ? average(second) : [(h1 + 40) % 360, s1];

        return paletteFrom(h1, s1, h2, s2);
    }

    const tone = (h, s, l) => `hsl(${Math.round(h)} ${Math.round(s)}% ${Math.round(l)}%)`;

    // Sin color que leer: luz neutra y cristal de hielo.
    const neutralPalette = () =>
        isDark()
            ? { colors: [tone(215, 12, 46), tone(35, 10, 44)], crystal: [tone(202, 32, 46), tone(258, 26, 42)] }
            : { colors: [tone(215, 12, 58), tone(35, 12, 60)], crystal: [tone(198, 55, 84), tone(262, 45, 86)] };

    // Dos lecturas del mismo par de tonos: apagada para la luz de la página
    // (interfaz sobria) y más viva para los botones de cristal. En un tema
    // oscuro el cristal es profundo, como vidrio tintado; en uno claro, pálido.
    function paletteFrom(h1, s1, h2, s2) {
        const muted = (s) => Math.min(54, Math.max(32, s * 72));
        const jewel = (s) => Math.min(90, Math.max(60, s * 125));

        return isDark()
            ? {
                  colors: [tone(h1, muted(s1), 52), tone(h2, muted(s2), 48)],
                  crystal: [tone(h1, jewel(s1) - 14, 50), tone(h2, jewel(s2) - 14, 43)],
              }
            : {
                  colors: [tone(h1, muted(s1), 56), tone(h2, muted(s2), 54)],
                  crystal: [tone(h1, jewel(s1), 80), tone(h2, jewel(s2), 77)],
              };
    }

    function toHsl(r, g, b) {
        r /= 255;
        g /= 255;
        b /= 255;

        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const l = (max + min) / 2;
        const d = max - min;
        if (d === 0) return [0, 0, l];

        const s = d / (1 - Math.abs(2 * l - 1));
        const h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;

        return [(h * 60 + 360) % 360, s, l];
    }

    // Sin portada legible: un tono estable derivado del ID, para que cada canción tenga su luz.
    function fallbackPalette(videoId) {
        const hue = [...videoId].reduce((sum, char) => (sum * 31 + char.charCodeAt(0)) % 360, 7);

        return paletteFrom(hue, 0.6, (hue + 45) % 360, 0.6);
    }

    const GLOW_VARIABLES = ['--glow-1', '--glow-2', '--crystal-1', '--crystal-2'];

    function applyGlow(palette = state.glow) {
        if (!palette) return;
        state.glow = palette;

        const values = [...palette.colors, ...palette.crystal];
        for (const element of [document.documentElement, root]) {
            GLOW_VARIABLES.forEach((name, index) => element.style.setProperty(name, values[index]));
        }
        highlight();
    }

    // Sin canción, la página vuelve a la luz propia del tema.
    function clearGlow() {
        state.glow = null;
        for (const element of [document.documentElement, root]) {
            GLOW_VARIABLES.forEach((name) => element.style.removeProperty(name));
        }
    }

    /* ---------- Controles ---------- */

    ui.toggle.addEventListener('click', toggle);
    ui.next.addEventListener('click', next);
    ui.prev.addEventListener('click', prev);
    ui.shuffle.addEventListener('click', () => {
        setShuffle(!state.shuffle);
        renderQueue();
    });
    ui.dj.addEventListener('click', () => {
        setDj(!state.dj);
        renderQueue();
    });

    ui.artist?.addEventListener('click', () => {
        const track = current();
        if (track?.artist) navigateToArtist(track.artist);
    });

    ui['cover-trigger']?.addEventListener('click', () => {
        if (current()) openCoverModal(current());
    });

    ui['cover-backdrop']?.addEventListener('click', closeCoverModal);
    ui['cover-close']?.addEventListener('click', closeCoverModal);

    ui['cover-modal-artist']?.addEventListener('click', () => {
        const artistName = state.modalTrack?.artist;
        closeCoverModal();
        if (artistName) navigateToArtist(artistName);
    });

    ui['cover-modal-enqueue']?.addEventListener('click', () => {
        if (state.modalTrack?.video_id) {
            enqueueTrack(state.modalTrack);
            closeCoverModal();
        }
    });

    ui['cover-modal-offline']?.addEventListener('click', () => {
        if (state.modalTrack?.video_id) {
            toggleTrackOffline(state.modalTrack);
        }
    });

    ui['offline-btn']?.addEventListener('click', () => {
        const track = current();
        if (track?.video_id) {
            toggleTrackOffline(track);
        }
    });

    ui['cover-modal-play']?.addEventListener('click', () => {
        const target = state.modalTrack;
        if (!target?.video_id) return;
        closeCoverModal();

        const existingIdx = state.queue.findIndex((t) => t.video_id === target.video_id);
        if (existingIdx >= 0) {
            if (existingIdx === state.index) {
                toggle();
            } else {
                state.wantsToPlay = true;
                load(existingIdx);
            }
            return;
        }

        state.queue.unshift(target);
        if (state.index >= 0) state.index++; // la que sonaba se ha desplazado un puesto
        state.failures = 0;
        state.wantsToPlay = true;
        load(0);
        renderQueue();
    });

    ui['queue-toggle']?.addEventListener('click', () => toggleQueuePanel());
    ui['queue-toggle-desktop']?.addEventListener('click', () => toggleQueuePanel());
    ui['queue-close']?.addEventListener('click', () => toggleQueuePanel(false));
    ui['queue-clear']?.addEventListener('click', () => clearQueue());

    ui['queue-list']?.addEventListener('click', (event) => {
        const removeBtn = event.target.closest('[data-queue-remove]');
        if (removeBtn) {
            removeFromQueue(Number(removeBtn.dataset.queueRemove));
            return;
        }

        const zoomBtn = event.target.closest('[data-queue-zoom]');
        if (zoomBtn) {
            const idx = Number(zoomBtn.dataset.queueZoom);
            if (state.queue[idx]) openCoverModal(state.queue[idx]);
            return;
        }

        const playBtn = event.target.closest('[data-queue-play]');
        if (playBtn) {
            const idx = Number(playBtn.dataset.queuePlay);
            if (idx === state.index) {
                toggle();
            } else if (state.queue[idx]) {
                state.wantsToPlay = true;
                load(idx);
            }
        }
    });

    speedToggles.forEach((button) => button.addEventListener('click', () => toggleSpeedPanel()));

    ui['speed-panel'].addEventListener('click', (event) => {
        const option = event.target.closest('[data-speed]');
        if (!option) return;

        setRate(Number(option.dataset.speed));
        toggleSpeedPanel(false);
    });

    // Un clic fuera del panel de velocidad lo cierra.
    document.addEventListener('click', (event) => {
        const inside = event.target.closest('[data-el="speed-panel"], [data-speed-toggle]');
        if (!inside && ui['speed-panel'].dataset.open === 'true') toggleSpeedPanel(false);
    });

    const seekTime = () => (ui.seek.value / 1000) * (audio.duration || 0);

    ui.seek.addEventListener('input', () => {
        state.seeking = true;
        renderProgress(seekTime());
    });

    ui.seek.addEventListener('change', () => {
        if (Number.isFinite(audio.duration)) {
            stopFade();
            audio.currentTime = seekTime();
            state.position = audio.currentTime;
        }
        state.seeking = false;
    });

    ui.volume.addEventListener('input', () => {
        state.muted = false;
        state.volume = ui.volume.value / 100;
        store('volume', state.volume);
        applyVolume();
        renderVolume();
    });

    ui.mute.addEventListener('click', () => {
        state.muted = !state.muted;
        if (!state.muted && state.volume === 0) state.volume = 0.5;
        applyVolume();
        renderVolume();
    });

    ui.favorite.addEventListener('click', async () => {
        const track = current();
        if (!track) return;

        const { video_id, title, artist, thumbnail_url } = track;
        const favorite = await $wire.toggleFavorite({ video_id, title, artist, thumbnail_url });

        if (isCurrent(track)) setFavorite(favorite);
        Livewire.dispatch('library-changed'); // las páginas refrescan sus corazones
    });

    // Canción de la fila o tarjeta sobre la que ocurre un evento.
    function trackFrom(element) {
        const row = element?.closest?.('[data-track]');
        if (!row?.dataset.track) return null;

        try {
            return JSON.parse(row.dataset.track);
        } catch {
            return null; // JSON no válido
        }
    }

    // Delegación global de clics para reproducir, encolar, guardar offline y ver portadas en grande.
    document.addEventListener('click', (event) => {
        const offlineTrigger = event.target.closest('[data-save-offline]');
        if (offlineTrigger) {
            event.preventDefault();
            const track = trackFrom(offlineTrigger);
            if (track) toggleTrackOffline(track);
            return;
        }

        const zoomTrigger = event.target.closest('[data-zoom-cover]');
        if (zoomTrigger) {
            event.preventDefault();
            const rawAttr = zoomTrigger.getAttribute('data-zoom-cover');
            const row = zoomTrigger.closest('[data-track]');
            const rawData = rawAttr && rawAttr.startsWith('{') ? rawAttr : row?.dataset.track;
            if (rawData) {
                try {
                    openCoverModal(JSON.parse(rawData));
                } catch {
                    // JSON no válido
                }
            }
            return;
        }

        const enqueueTrigger = event.target.closest('[data-enqueue]');
        if (enqueueTrigger) {
            const track = trackFrom(enqueueTrigger);
            if (track) enqueueTrack(track);
            return;
        }

        const enqueueAllTrigger = event.target.closest('[data-enqueue-all]');
        if (enqueueAllTrigger) {
            const list = enqueueAllTrigger.closest('[data-track-list]');
            if (list) {
                const tracks = [...list.querySelectorAll('[data-track]')].map((row) => JSON.parse(row.dataset.track));
                enqueueTracks(tracks);
            }
            return;
        }

        const trigger = event.target.closest('[data-play], [data-play-all]');
        const list = trigger?.closest('[data-track-list]');
        if (!list) return;

        const rows = [...list.querySelectorAll('[data-track]')];
        const tracks = rows.map((row) => JSON.parse(row.dataset.track));
        if (!tracks.length) return;

        // "Aleatorio" de una lista: enciende el modo y empieza por una canción al azar.
        if (trigger.hasAttribute('data-shuffle')) {
            setShuffle(true, { silent: true });
            return playQueue(tracks, Math.floor(Math.random() * tracks.length));
        }

        const index = Math.max(0, rows.indexOf(trigger.closest('[data-track]')));

        // Pulsar la canción que ya está cargada alterna play/pausa (y adopta esta lista como cola).
        if (trigger.hasAttribute('data-play') && isCurrent(tracks[index])) {
            state.queue = tracks;
            state.index = index;
            state.bag = [];
            renderQueue();
            return toggle();
        }

        playQueue(tracks, index);
    });

    // Carga anticipada: al empezar el clic sobre una canción, o al dejar el
    // puntero encima un momento, su enlace se resuelve antes de que se suelte.
    let hoverTimer = null;

    document.addEventListener(
        'pointerdown',
        (event) => {
            if (event.target.closest?.('[data-play]')) warm(trackFrom(event.target));
        },
        { passive: true },
    );

    document.addEventListener(
        'pointerover',
        (event) => {
            if (event.pointerType !== 'mouse') return;

            clearTimeout(hoverTimer);
            const target = event.target.closest?.('[data-play]');
            if (target) hoverTimer = setTimeout(() => warm(trackFrom(target)), HOVER_INTENT);
        },
        { passive: true },
    );

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (ui['cover-modal']?.dataset.open === 'true') {
                closeCoverModal();
                return;
            }
            if (ui['speed-panel'].dataset.open === 'true') {
                toggleSpeedPanel(false);
                return;
            }
            if (ui['queue-panel']?.dataset.open === 'true') {
                toggleQueuePanel(false);
                return;
            }
        }

        if (event.code !== 'Space' || event.target.closest('input, textarea, select, button, a, [contenteditable]')) return;
        event.preventDefault();
        toggle();
    });

    // Teclas multimedia, auriculares y pantalla de bloqueo.
    if ('mediaSession' in navigator) {
        const actions = {
            play: () => state.wantsToPlay || toggle(),
            pause: () => state.wantsToPlay && toggle(),
            previoustrack: prev,
            nexttrack: next,
            seekto: ({ seekTime: time }) => (audio.currentTime = time),
        };

        for (const [action, handler] of Object.entries(actions)) {
            try {
                navigator.mediaSession.setActionHandler(action, handler);
            } catch {
                // acción no soportada por este navegador
            }
        }
    }

    // Eventos que emiten las páginas Livewire (Search / Library).
    Livewire.on('favorite-changed', (payload) => {
        const { videoId, favorite } = detail(payload);
        const known = streams.get(videoId);

        if (known?.result) known.result.is_favorite = Boolean(favorite);
        if (current()?.video_id === videoId) setFavorite(favorite);
    });

    // Una canción excluida sale de la fila; si era la que sonaba, pasa a la siguiente.
    Livewire.on('track-excluded', (payload) => {
        const { videoId } = detail(payload);

        for (let index = state.queue.length - 1; index >= 0; index--) {
            if (state.queue[index].video_id === videoId) removeFromQueue(index);
        }
    });

    Livewire.on('toast', (payload) => toast(detail(payload).message ?? ''));

    Livewire.on('offline-remove-blob', (payload) => {
        const { videoId } = detail(payload);
        if (videoId) {
            removeTrackOffline(videoId, { notify: true });
        }
    });

    document.addEventListener('livewire:navigated', () => {
        applyGlow();
        syncOfflineBadges();
    });

    // Al cambiar de tema, la luz de la canción se recalcula para el nuevo esquema (claro u oscuro).
    window.addEventListener('onda:theme-applied', () => {
        const track = current();
        if (track) updateGlow(track);
    });

    if (window.Livewire?.hook) {
        window.Livewire.hook('morph.updated', () => syncOfflineBadges());
    }

    // Safari solo deja sonar sin un gesto del usuario a un <audio> que ya haya
    // sonado con uno. En el primer toque se "estrena" el plato libre con un
    // silencio, para que luego pueda entrar solo con la canción siguiente.
    function silentClip() {
        const samples = 2205; // 50 ms a 44,1 kHz
        const view = new DataView(new ArrayBuffer(44 + samples * 2));
        const text = (offset, value) => [...value].forEach((char, i) => view.setUint8(offset + i, char.charCodeAt(0)));

        text(0, 'RIFF');
        view.setUint32(4, 36 + samples * 2, true);
        text(8, 'WAVEfmt ');
        view.setUint32(16, 16, true);
        view.setUint16(20, 1, true);
        view.setUint16(22, 1, true);
        view.setUint32(24, 44100, true);
        view.setUint32(28, 88200, true);
        view.setUint16(32, 2, true);
        view.setUint16(34, 16, true);
        text(36, 'data');
        view.setUint32(40, samples * 2, true);

        return URL.createObjectURL(new Blob([view.buffer], { type: 'audio/wav' }));
    }

    function unlockIdleDeck() {
        const deck = idleDeck();
        if (deck.getAttribute('src')) return;

        const clip = silentClip();
        deck.muted = true;
        deck.src = clip;
        deck
            .play()
            .catch(() => {})
            .finally(() => {
                if (deck.getAttribute('src') === clip) releaseDeck(deck);
                deck.muted = state.muted;
                URL.revokeObjectURL(clip);
            });
    }

    document.addEventListener('pointerdown', unlockIdleDeck, { once: true, capture: true });
    document.addEventListener('keydown', unlockIdleDeck, { once: true, capture: true });

    /* ---------- Arranque ---------- */

    const savedVolume = parseFloat(store('volume'));
    state.volume = Number.isFinite(savedVolume) ? Math.min(1, Math.max(0, savedVolume)) : 0.8;
    applyVolume();
    renderVolume();
    setRate(parseFloat(store('rate')));
    setShuffle(store('shuffle') === '1', { silent: true });
    setDj(store('dj') === '1', { silent: true });
    refreshStatus();
    renderQueue();
    syncOfflineBadges();

    // Útil desde la consola o desde otros scripts.
    window.musicPlayer = {
        playQueue,
        enqueueTrack,
        enqueueTracks,
        openCoverModal,
        toggleTrackOffline,
        removeTrackOffline,
        toggle,
        next,
        prev,
        setRate,
        setShuffle,
        setDj,
        state,
    };
</script>
@endscript
