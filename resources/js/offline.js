/*
 | Modo sin conexión (resources/views/offline.blade.php).
 |
 | Lee las canciones que el reproductor guardó en este navegador (IndexedDB
 | «onda-offline-v1», el mismo almacén que usa la app con conexión) y las
 | reproduce. No hace ninguna petición a la red: todo sale del dispositivo.
 */
import { boot as bootBackdrop } from './backdrop';

bootBackdrop();

const DB_NAME = 'onda-offline-v1';
const STORE = 'tracks';
const PREFERENCES = 'onda:offline-player';
const RESTART_AFTER = 4; // «Anterior» vuelve al principio si la canción ya lleva estos segundos

const root = document.querySelector('[data-offline-player]');
const ui = Object.fromEntries([...root.querySelectorAll('[data-el]')].map((element) => [element.dataset.el, element]));
const audio = ui.audio;
const rowTemplate = document.getElementById('offline-track-row');

const state = {
    tracks: [],
    /** Orden de reproducción: índices de `tracks` (barajados en aleatorio). */
    order: [],
    /** Posición dentro de `order`; -1 si no hay nada elegido. */
    position: -1,
    shuffle: false,
    repeat: false,
    seeking: false,
    audioUrl: null,
    coverUrls: new Map(),
};

/* ---------- Utilidades ---------- */

const megabytes = (bytes) => (bytes / (1024 * 1024)).toFixed(1);

function clock(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) return '0:00';

    const total = Math.floor(seconds);

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

function preferences() {
    try {
        return JSON.parse(localStorage.getItem(PREFERENCES)) || {};
    } catch {
        return {};
    }
}

function remember(change) {
    try {
        localStorage.setItem(PREFERENCES, JSON.stringify({ ...preferences(), ...change }));
    } catch {}
}

function shuffled(items) {
    const copy = [...items];

    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }

    return copy;
}

function notice(message) {
    ui.notice.textContent = message;
    ui.notice.classList.toggle('hidden', !message);
}

const currentTrack = () => state.tracks[state.order[state.position]] ?? null;

/* ---------- Canciones guardadas ---------- */

function loadTracks() {
    return new Promise((resolve) => {
        if (!('indexedDB' in window)) return resolve([]);

        const request = indexedDB.open(DB_NAME, 1);

        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE)) {
                request.result.createObjectStore(STORE, { keyPath: 'video_id' });
            }
        };
        request.onerror = () => resolve([]);
        request.onsuccess = () => {
            const db = request.result;
            const read = db.transaction(STORE, 'readonly').objectStore(STORE).getAll();

            read.onerror = () => resolve([]);
            read.onsuccess = () => {
                db.close();
                resolve((read.result || [])
                    .filter((track) => track?.blob instanceof Blob && track.blob.size > 0)
                    .sort((a, b) => (b.saved_at || 0) - (a.saved_at || 0)));
            };
        };
    });
}

/** Portada guardada con la canción; si no la hay, la de internet (por si está en la caché del navegador). */
function coverFor(track) {
    if (track.cover instanceof Blob) {
        if (!state.coverUrls.has(track.video_id)) state.coverUrls.set(track.video_id, URL.createObjectURL(track.cover));

        return state.coverUrls.get(track.video_id);
    }

    return track.thumbnail_url || '';
}

function showCover(image, track) {
    const url = coverFor(track);

    image.classList.add('opacity-0');
    image.onload = () => image.classList.remove('opacity-0');
    image.onerror = () => image.classList.add('opacity-0');

    if (url) image.src = url;
    else image.removeAttribute('src');
}

function renderList() {
    const count = state.tracks.length;
    const bytes = state.tracks.reduce((sum, track) => sum + (track.size_bytes || track.blob.size), 0);

    ui.summary.textContent = count
        ? `${count} ${count === 1 ? 'canción guardada' : 'canciones guardadas'} en este dispositivo · ${megabytes(bytes)} MB`
        : 'No hay canciones guardadas en este dispositivo.';

    ui.empty.classList.toggle('hidden', count > 0);
    ui.list.classList.toggle('hidden', count === 0);
    ui.actions.classList.toggle('hidden', count === 0);
    ui.actions.classList.toggle('flex', count > 0);

    ui.list.replaceChildren(...state.tracks.map((track, index) => {
        const row = rowTemplate.content.firstElementChild.cloneNode(true);

        row.dataset.index = String(index);
        row.querySelector('.track-index').textContent = String(index + 1);
        row.querySelector('[data-title]').textContent = track.title || 'Canción';
        row.querySelector('[data-artist]').textContent = track.artist || '';
        row.querySelector('[data-size]').textContent = `${megabytes(track.size_bytes || track.blob.size)} MB`;
        row.querySelector('[data-duration]').textContent = track.duration > 0 ? clock(track.duration) : '';
        row.querySelectorAll('[data-play]').forEach((button) => button.setAttribute('aria-label', `Reproducir ${track.title || 'canción'}`));
        showCover(row.querySelector('[data-cover]'), track);

        return row;
    }));
}

/* ---------- Reproducción ---------- */

function setStatus(status) {
    root.dataset.status = status;
    ui.toggle.setAttribute('aria-label', status === 'playing' ? 'Pausar' : 'Reproducir');
}

function highlight() {
    const active = state.order[state.position];

    ui.list.querySelectorAll('.track-row').forEach((row) => {
        row.toggleAttribute('data-current', Number(row.dataset.index) === active);
    });
}

function buildOrder(startIndex = 0) {
    const indices = state.tracks.map((_, index) => index);

    if (!state.shuffle) {
        state.order = indices;
        state.position = startIndex;

        return;
    }

    // En aleatorio, la canción elegida suena primero y el resto se baraja.
    state.order = [startIndex, ...shuffled(indices.filter((index) => index !== startIndex))];
    state.position = 0;
}

function describe(track) {
    ui.title.textContent = track.title || 'Canción';
    ui.artist.textContent = track.artist || '';
    showCover(ui.cover, track);
    document.title = `${track.title || 'Canción'} · Sin conexión · Onda`;

    if ('mediaSession' in navigator) {
        const cover = coverFor(track);

        navigator.mediaSession.metadata = new MediaMetadata({
            title: track.title || 'Canción',
            artist: track.artist || '',
            album: 'Onda · sin conexión',
            artwork: cover ? [{ src: cover, sizes: '512x512' }] : [],
        });
    }
}

async function playAt(position) {
    if (position < 0 || position >= state.order.length) return;

    state.position = position;

    const track = currentTrack();
    if (!track) return;

    notice('');
    describe(track);
    highlight();

    if (state.audioUrl) URL.revokeObjectURL(state.audioUrl);
    state.audioUrl = URL.createObjectURL(track.blob);

    audio.src = state.audioUrl;
    ui.seek.disabled = false;

    try {
        await audio.play();
    } catch (error) {
        // AbortError: se eligió otra canción antes de que esta empezara. No es un fallo.
        if (error?.name !== 'AbortError') setStatus('paused');
    }
}

function playTrack(index) {
    buildOrder(index);
    playAt(state.position);
}

function next({ auto = false } = {}) {
    if (!state.order.length) return;

    if (state.position + 1 < state.order.length) return playAt(state.position + 1);

    // Fin de la lista: se vuelve a empezar si se pulsó «Siguiente» o está activado repetir.
    if (auto && !state.repeat) {
        setStatus('paused');

        return;
    }

    if (state.shuffle) buildOrder(state.order[0]);
    playAt(0);
}

function previous() {
    if (!state.order.length) return;

    if (audio.currentTime > RESTART_AFTER || state.position === 0) {
        audio.currentTime = 0;

        return;
    }

    playAt(state.position - 1);
}

function toggle() {
    if (!state.tracks.length) return;
    if (state.position < 0) return playTrack(0);

    audio.paused ? audio.play().catch(() => {}) : audio.pause();
}

function setShuffle(active) {
    state.shuffle = active;
    ui.shuffle.dataset.active = String(active);
    ui.shuffle.setAttribute('aria-pressed', String(active));
    remember({ shuffle: active });

    // Se rehace el orden sin cortar la canción que suena.
    if (state.position >= 0) buildOrder(state.order[state.position]);
}

function setRepeat(active) {
    state.repeat = active;
    ui.repeat.dataset.active = String(active);
    ui.repeat.setAttribute('aria-pressed', String(active));
    remember({ repeat: active });
}

function setVolume(level) {
    const volume = Math.max(0, Math.min(100, Number(level) || 0));

    audio.volume = volume / 100;
    audio.muted = volume === 0;
    ui.volume.value = String(volume);
    ui.volume.style.setProperty('--progress', `${volume}%`);
    root.dataset.muted = String(audio.muted);
    remember({ volume });
}

function paintProgress() {
    const duration = audio.duration;
    const ratio = Number.isFinite(duration) && duration > 0 ? audio.currentTime / duration : 0;

    ui.current.textContent = clock(audio.currentTime);
    ui.duration.textContent = clock(Number.isFinite(duration) ? duration : currentTrack()?.duration || 0);

    if (!state.seeking) {
        ui.seek.value = String(Math.round(ratio * 1000));
        ui.seek.style.setProperty('--progress', `${ratio * 100}%`);
    }
}

/* ---------- Eventos ---------- */

audio.addEventListener('playing', () => setStatus('playing'));
audio.addEventListener('pause', () => !audio.ended && setStatus('paused'));
audio.addEventListener('timeupdate', paintProgress);
audio.addEventListener('durationchange', paintProgress);
audio.addEventListener('ended', () => next({ auto: true }));
audio.addEventListener('error', () => {
    const track = currentTrack();
    if (!track || !audio.src) return;

    notice(`«${track.title || 'Esta canción'}» no se pudo reproducir en este dispositivo.`);
    setStatus('paused');
});

ui.list.addEventListener('click', (event) => {
    const row = event.target.closest('[data-play]')?.closest('.track-row');
    if (!row) return;

    const index = Number(row.dataset.index);

    // Un toque en la canción que ya suena la pausa o la reanuda.
    index === state.order[state.position] ? toggle() : playTrack(index);
});

ui.toggle.addEventListener('click', toggle);
ui.next.addEventListener('click', () => next());
ui.prev.addEventListener('click', previous);
ui.shuffle.addEventListener('click', () => setShuffle(!state.shuffle));
ui.repeat.addEventListener('click', () => setRepeat(!state.repeat));
ui['play-all'].addEventListener('click', () => {
    setShuffle(false);
    playTrack(0);
});
ui['shuffle-all'].addEventListener('click', () => {
    setShuffle(true);
    playTrack(Math.floor(Math.random() * state.tracks.length));
});

ui.seek.addEventListener('input', () => {
    state.seeking = true;
    ui.seek.style.setProperty('--progress', `${ui.seek.value / 10}%`);

    if (Number.isFinite(audio.duration)) ui.current.textContent = clock((ui.seek.value / 1000) * audio.duration);
});
ui.seek.addEventListener('change', () => {
    if (Number.isFinite(audio.duration)) audio.currentTime = (ui.seek.value / 1000) * audio.duration;

    state.seeking = false;
});

ui.volume.addEventListener('input', () => setVolume(ui.volume.value));
ui.mute.addEventListener('click', () => {
    audio.muted = !audio.muted;
    root.dataset.muted = String(audio.muted);
});

document.addEventListener('keydown', (event) => {
    if (event.code !== 'Space' || event.target.closest('button, input, a, textarea, select')) return;

    event.preventDefault();
    toggle();
});

if ('mediaSession' in navigator) {
    const actions = {
        play: () => audio.play().catch(() => {}),
        pause: () => audio.pause(),
        previoustrack: previous,
        nexttrack: () => next(),
        seekto: ({ seekTime }) => Number.isFinite(seekTime) && (audio.currentTime = seekTime),
    };

    for (const [action, handler] of Object.entries(actions)) {
        try {
            navigator.mediaSession.setActionHandler(action, handler);
        } catch {
            // Acción no disponible en este navegador.
        }
    }
}

/* ---------- Estado de la red ---------- */

function paintNetwork() {
    const online = navigator.onLine;

    root.dataset.network = online ? 'online' : 'offline';
    ui.network.textContent = online ? 'Hay conexión' : 'Sin conexión';
}

window.addEventListener('online', paintNetwork);
window.addEventListener('offline', paintNetwork);

/* ---------- Arranque ---------- */

async function boot() {
    const saved = preferences();

    paintNetwork();
    setVolume(Number.isFinite(saved.volume) ? saved.volume : 80);
    setShuffle(Boolean(saved.shuffle));
    setRepeat(Boolean(saved.repeat));

    state.tracks = await loadTracks();
    renderList();
}

boot();
