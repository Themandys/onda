/*
 | Fondo personalizado de la interfaz: una imagen o un video ligero que la
 | persona elige en su perfil.
 |
 | El archivo no se sube al servidor: se guarda en este navegador (IndexedDB),
 | así que no ocupa espacio en el hosting, no tiene límites de subida y sigue
 | ahí en el modo sin conexión. Lo que sí es de cada dispositivo: en otro
 | navegador hay que elegirlo de nuevo.
 */

const DB_NAME = 'onda-appearance-v1';
const STORE = 'media';
const RECORD_KEY = 'backdrop';
const SETTINGS_KEY = 'onda:backdrop';
const MB = 1024 * 1024;

export const DEFAULT_DIM = 55;
export const MAX_DIM = 90;

export const LIMITS = {
    /** Lado mayor (px) al que se reduce una imagen: de sobra para una pantalla 2K. */
    imageSide: 2560,
    /** Las imágenes animadas se guardan tal cual, así que tienen tope de peso. */
    gifBytes: 12 * MB,
    /** "Videos ligeros": un bucle corto. Más pesa, más tarda en arrancar y más batería gasta. */
    videoBytes: 30 * MB,
};

export class BackdropError extends Error {}

const root = document.documentElement;
const megabytes = (bytes) => (bytes / MB).toFixed(1);
const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

let objectUrl = null;

/* ---------- Almacenamiento ---------- */

function openDb() {
    return new Promise((resolve, reject) => {
        if (!('indexedDB' in window)) return reject(new BackdropError('Este navegador no permite guardar el fondo.'));

        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE)) request.result.createObjectStore(STORE);
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function transact(mode, run) {
    const db = await openDb();

    try {
        return await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, mode);
            const request = run(tx.objectStore(STORE));
            tx.oncomplete = () => resolve(request?.result ?? null);
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(tx.error);
        });
    } finally {
        db.close();
    }
}

const readRecord = () => transact('readonly', (store) => store.get(RECORD_KEY));
const writeRecord = (record) => transact('readwrite', (store) => store.put(record, RECORD_KEY));
const deleteRecord = () => transact('readwrite', (store) => store.delete(RECORD_KEY));

function readSettings() {
    try {
        return JSON.parse(localStorage.getItem(SETTINGS_KEY)) || {};
    } catch {
        return {};
    }
}

function writeSettings(settings) {
    try {
        if (settings) localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
        else localStorage.removeItem(SETTINGS_KEY);
    } catch {
        // Almacenamiento bloqueado: el fondo funciona igual, solo tarda un instante más en aparecer.
    }
}

/* ---------- Pintado ---------- */

function host() {
    return document.getElementById('onda-backdrop');
}

function applyDim(dim) {
    root.style.setProperty('--backdrop-dim', (clamp(Number(dim) || 0, 0, MAX_DIM) / 100).toFixed(2));
}

function describe(record) {
    return record ? { kind: record.kind, name: record.name, size: record.size, url: objectUrl } : null;
}

function unmount() {
    const element = host();

    delete root.dataset.backdrop;
    root.style.removeProperty('--backdrop-dim');

    if (element) {
        element.replaceChildren();
        delete element.dataset.stamp;
        element.dataset.ready = 'false';
    }

    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
}

function render(element, record) {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(record.blob);

    element.dataset.stamp = String(record.saved_at);
    element.dataset.ready = 'false';

    const reveal = () => (element.dataset.ready = 'true');
    let media;

    if (record.kind === 'video') {
        const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        media = document.createElement('video');
        media.muted = true;
        media.defaultMuted = true;
        media.loop = true;
        media.playsInline = true;
        media.autoplay = !still;
        media.preload = 'auto';
        media.disablePictureInPicture = true;
        media.setAttribute('muted', '');
        media.setAttribute('playsinline', '');
        media.addEventListener('loadeddata', () => {
            reveal();
            if (!still) media.play().catch(() => {});
        }, { once: true });
    } else {
        media = new Image();
        media.alt = '';
        media.decoding = 'async';
        media.addEventListener('load', reveal, { once: true });
    }

    media.className = 'backdrop-media';
    media.src = objectUrl;
    element.replaceChildren(media);
}

/**
 * Muestra el fondo guardado (si lo hay) en la página actual. Se puede llamar
 * las veces que haga falta: si ya está puesto, no hace nada.
 */
export async function mount() {
    const element = host();
    if (!element) return null;

    let record = null;
    try {
        record = await readRecord();
    } catch {
        record = null;
    }

    if (!record?.blob) {
        if (root.dataset.backdrop || readSettings().kind) {
            unmount();
            writeSettings(null);
        }

        return null;
    }

    const settings = readSettings();
    const dim = Number.isFinite(settings.dim) ? settings.dim : DEFAULT_DIM;
    if (settings.kind !== record.kind || settings.dim !== dim) writeSettings({ kind: record.kind, dim });

    root.dataset.backdrop = record.kind;
    applyDim(dim);

    if (element.dataset.stamp !== String(record.saved_at) || !element.firstElementChild) render(element, record);

    return describe(record);
}

/* ---------- Elegir, quitar y ajustar ---------- */

function probeVideo(file) {
    return new Promise((resolve, reject) => {
        const video = document.createElement('video');
        const url = URL.createObjectURL(file);
        const finish = (playable) => {
            clearTimeout(timer);
            URL.revokeObjectURL(url);
            playable
                ? resolve()
                : reject(new BackdropError('Este navegador no puede reproducir ese video. Prueba con un MP4 o un WebM.'));
        };
        const timer = setTimeout(() => finish(false), 10000);

        video.preload = 'metadata';
        video.muted = true;
        video.onloadedmetadata = () => finish(video.videoWidth > 0);
        video.onerror = () => finish(false);
        video.src = url;
    });
}

async function shrinkImage(file) {
    let bitmap;
    try {
        bitmap = await createImageBitmap(file);
    } catch {
        throw new BackdropError('No se pudo leer esa imagen. Prueba con una foto JPG, PNG o WebP.');
    }

    const scale = Math.min(1, LIMITS.imageSide / Math.max(bitmap.width, bitmap.height));

    // Una foto que ya es pequeña se guarda tal cual: recomprimirla solo le quitaría calidad.
    if (scale === 1 && file.type === 'image/jpeg' && file.size <= 2 * MB) {
        bitmap.close?.();

        return file;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);

    const context = canvas.getContext('2d');
    context.fillStyle = getComputedStyle(root).getPropertyValue('--color-paper').trim() || '#000';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86));
    if (!blob) throw new BackdropError('No se pudo preparar esa imagen. Prueba con otra.');

    return blob;
}

/**
 * Guarda el archivo como fondo y lo muestra. Lanza BackdropError con un
 * mensaje para la persona si el archivo no sirve.
 */
export async function set(file) {
    try {
        return await store(file);
    } catch (error) {
        // A la pantalla siempre le llega un mensaje que se pueda enseñar.
        throw error instanceof BackdropError ? error : new BackdropError('No se pudo usar ese archivo. Prueba con otro.');
    }
}

async function store(file) {
    const kind = file?.type?.startsWith('video/') ? 'video' : file?.type?.startsWith('image/') ? 'image' : null;
    if (!kind) throw new BackdropError('Elige una imagen (JPG, PNG, WebP o GIF) o un video (MP4 o WebM).');

    let blob = file;

    if (kind === 'video') {
        if (file.size > LIMITS.videoBytes) {
            throw new BackdropError(`Ese video pesa ${megabytes(file.size)} MB. Usa uno ligero, de hasta ${LIMITS.videoBytes / MB} MB.`);
        }

        await probeVideo(file);
    } else if (file.type === 'image/gif') {
        if (file.size > LIMITS.gifBytes) {
            throw new BackdropError(`Ese GIF pesa ${megabytes(file.size)} MB. Usa uno de hasta ${LIMITS.gifBytes / MB} MB.`);
        }
    } else {
        blob = await shrinkImage(file);
    }

    try {
        await writeRecord({ kind, blob, type: blob.type, name: file.name, size: blob.size, saved_at: Date.now() });
    } catch {
        throw new BackdropError('Este navegador no ha podido guardar ese fondo. Prueba con un archivo más ligero.');
    }

    writeSettings({ kind, dim: Number.isFinite(readSettings().dim) ? readSettings().dim : DEFAULT_DIM });

    return announce(await mount());
}

export async function clear() {
    try {
        await deleteRecord();
    } catch {
        // Si no se puede borrar, al menos deja de mostrarse.
    }

    writeSettings(null);
    unmount();

    return announce(null);
}

/** Cuánto cubre el color del tema al fondo (0-90): más alto, más legible el texto. */
export function setDim(value) {
    const dim = clamp(Math.round(Number(value)) || 0, 0, MAX_DIM);
    const settings = readSettings();

    if (settings.kind) writeSettings({ ...settings, dim });
    applyDim(dim);

    return dim;
}

export function dim() {
    const saved = readSettings().dim;

    return Number.isFinite(saved) ? saved : DEFAULT_DIM;
}

function announce(summary) {
    window.dispatchEvent(new CustomEvent('onda:backdrop-changed', { detail: summary }));

    return summary;
}

/* ---------- Arranque ---------- */

export function boot() {
    window.OndaBackdrop = { mount, set, clear, setDim, dim, LIMITS, DEFAULT_DIM, MAX_DIM };

    mount();

    // wire:navigate puede traer un <body> nuevo: se vuelve a comprobar que el fondo sigue puesto.
    document.addEventListener('livewire:navigated', () => mount());

    // Un video de fondo no tiene por qué gastar batería con la pestaña oculta.
    document.addEventListener('visibilitychange', () => {
        const video = host()?.querySelector('video');
        if (!video || !video.autoplay) return;

        document.hidden ? video.pause() : video.play().catch(() => {});
    });
}
