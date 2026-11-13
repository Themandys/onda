{{--
    Botones de una lista de canciones: reproducir (cristal), aleatorio y
    añadir a la fila. Van dentro del contenedor [data-track-list] de la lista.
    Variables: $label (string, qué se reproduce: "la playlist", "el género"…).
--}}
<button type="button" data-play-all class="crystal crystal-strong size-12" aria-label="Reproducir {{ $label ?? 'todo' }}" title="Reproducir todo">
    <svg viewBox="0 0 24 24" class="size-6 translate-x-px" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.5-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14Z" /></svg>
</button>

<button type="button" data-play-all data-shuffle class="btn-secondary flex items-center gap-1.5 rounded-full px-3.5 py-2 text-xs font-medium transition">
    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 7h3.2a4 4 0 0 1 3.3 1.75l5 7.5A4 4 0 0 0 17.8 18H21m0 0-2.5-2.5M21 18l-2.5 2.5M3 17h3.2a4 4 0 0 0 3.3-1.75l.6-.9M21 6h-3.2a4 4 0 0 0-3.3 1.75l-.6.9M21 6l-2.5-2.5M21 6l-2.5 2.5" />
    </svg>
    Aleatorio
</button>

<button type="button" data-enqueue-all class="btn-secondary flex items-center gap-1.5 rounded-full px-3.5 py-2 text-xs font-medium transition">
    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3.75 6.75h10.5M3.75 12h10.5m-10.5 5.25h6.75M17.25 12v6m-3-3h6" />
    </svg>
    Añadir a la fila
</button>
