{{--
    Aplicación instalable y modo sin conexión: manifiesto, iconos y service worker.

    El service worker se registra con una versión que cambia en cada
    despliegue: así vuelve a instalarse y guarda de nuevo la pantalla del
    modo sin conexión con los archivos recién compilados.
--}}
@php
    $serviceWorkerVersion = substr(md5(implode('|', array_map(
        fn (string $path): string => is_file($path) ? (string) filemtime($path) : '',
        [public_path('build/manifest.json'), public_path('sw.js'), resource_path('views/offline.blade.php')],
    ))), 0, 10);
@endphp
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register(@js('/sw.js?v='.$serviceWorkerVersion)).catch(() => {}), { once: true });
    }
</script>
