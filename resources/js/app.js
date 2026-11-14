import { boot as bootBackdrop } from './backdrop';

bootBackdrop();

const RELOADED_AT = 'onda:reloaded-at';
const RELOAD_GUARD = 20000;

/**
 * ¿Se recargó la página hace un momento por un error del servidor? Si vuelve
 * a fallar tan pronto, no es una página caducada: recargar otra vez sería un
 * bucle, así que se deja que Livewire enseñe el error.
 */
function reloadedRecently() {
    try {
        return Date.now() - Number(sessionStorage.getItem(RELOADED_AT) || 0) < RELOAD_GUARD;
    } catch {
        return true;
    }
}

/*
 | Peticiones de Livewire que el servidor ya no reconoce: la sesión caducó
 | (419) o la página se abrió antes de un redespliegue y su dirección interna
 | cambió (404). En vez de la ventana de error de Livewire, se recarga la
 | página una vez; la pantalla de acceso explica qué pasó.
 */
function watchRequests() {
    window.Livewire.interceptRequest(({ onError, onFailure, onSuccess }) => {
        // Sin respuesta del servidor (sin red o servidor caído): la cabecera ofrece el modo sin conexión.
        onFailure(() => window.dispatchEvent(new CustomEvent('onda:unreachable')));
        onSuccess(() => window.dispatchEvent(new CustomEvent('onda:reachable')));

        onError(({ response, preventDefault }) => {
            if (response.status !== 419 && response.status !== 404) return;
            if (reloadedRecently()) return;

            preventDefault();

            try {
                sessionStorage.setItem(RELOADED_AT, String(Date.now()));
                sessionStorage.setItem('onda:expired', '1');
            } catch {}

            window.location.reload();
        });
    });
}

if (window.Livewire) watchRequests();
else document.addEventListener('livewire:init', watchRequests, { once: true });
