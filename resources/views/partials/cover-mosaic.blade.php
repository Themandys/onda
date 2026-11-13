{{--
    Mosaico de portadas de una colección. Variables:
      $covers  list<string>  URLs de portada (se usan las 4 primeras)
      $size    int           lado en px que se pide a cada portada
      $class   ?string       clases extra (tamaño)

    Con 4 portadas o más forma una cuadrícula 2×2; con menos, muestra la
    primera a tamaño completo y, sin ninguna, un icono.
--}}
@php
    $covers = array_values(array_filter($covers ?? []));
    $shown = count($covers) >= 4 ? array_slice($covers, 0, 4) : array_slice($covers, 0, 1);
@endphp

<div class="cover-mosaic {{ $class ?? '' }}" data-count="{{ count($shown) }}" aria-hidden="true">
    @forelse ($shown as $cover)
        <img
            src="{{ \App\Services\MusicStreamingService::thumbnail($cover, $size ?? 226) }}"
            alt=""
            loading="lazy"
            referrerpolicy="no-referrer"
        >
    @empty
        <svg viewBox="0 0 24 24" class="size-1/3" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m9 9 10.5-3m0 6.55v3.75a2.25 2.25 0 0 1-1.63 2.16l-1.32.38a1.8 1.8 0 1 1-.99-3.47l2.31-.66a2.25 2.25 0 0 0 1.63-2.16Zm0 0V2.25L9 5.25v10.3m0 0v3.75a2.25 2.25 0 0 1-1.63 2.16l-1.32.38a1.8 1.8 0 0 1-.99-3.47l2.31-.66A2.25 2.25 0 0 0 9 15.55Z" />
        </svg>
    @endforelse
</div>
