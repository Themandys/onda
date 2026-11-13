{{-- Marcador de carga para una lista de canciones. Variables: $rows (int, por defecto 6). --}}
<div class="glass flex flex-col gap-3 rounded-2xl p-4" aria-hidden="true">
    @for ($i = 0; $i < ($rows ?? 6); $i++)
        <div class="flex items-center gap-3">
            <div class="skeleton size-11 shrink-0"></div>
            <div class="flex-1">
                <div class="skeleton h-3.5 w-2/5"></div>
                <div class="skeleton mt-1.5 h-3 w-1/4"></div>
            </div>
        </div>
    @endfor
</div>
