<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Instancias de Piped API
    |--------------------------------------------------------------------------
    |
    | Lista ordenada por preferencia. Si una instancia falla (caída, 5xx,
    | bloqueo anti-bot de YouTube...) el servicio prueba la siguiente y deja
    | la que falló "en reposo" durante `cooldown` segundos.
    |
    | Las instancias públicas cambian a menudo: revisa
    | https://github.com/TeamPiped/documentation/blob/main/content/docs/public-instances/index.md
    | o, mejor, apunta aquí a tu propia instancia.
    |
    */

    'piped' => [
        'instances' => array_values(array_filter(array_map(
            fn (string $url) => rtrim(trim($url), '/'),
            explode(',', (string) env(
                'PIPED_INSTANCES',
                'https://pipedapi.ducks.party,https://api.piped.private.coffee,https://pipedapi.adminforge.de,https://pipedapi.drgns.space'
            ))
        ))),

        // Segundos máximos por petición a la API.
        'timeout' => (int) env('PIPED_TIMEOUT', 8),

        // Segundos que una instancia se deja de lado tras fallar.
        'cooldown' => (int) env('PIPED_COOLDOWN', 300),

        // Comprueba (petición Range de 2 bytes) que la URL del stream responde
        // antes de devolverla al reproductor. Evita entregar enlaces con 403.
        'verify_stream' => (bool) env('PIPED_VERIFY_STREAM', true),

        // Resuelve los hosts solo por IPv4. Con PHP-FPM en macOS (Herd/Valet)
        // la resolución dual IPv4+IPv6 de curl puede tumbar el worker (502).
        // Ponlo a false solo si tu red es exclusivamente IPv6.
        'force_ipv4' => (bool) env('PIPED_FORCE_IPV4', true),

        // Máximo de canciones por búsqueda o vista de artista (entre 50 y 100).
        'search_limit' => (int) env('MUSIC_SEARCH_LIMIT', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | Extractor directo (YouTube Innertube)
    |--------------------------------------------------------------------------
    |
    | Resuelve el stream directamente contra la API Innertube de YouTube desde
    | la IP local y lo sirve mediante el proxy de rangos (`/stream/{id}`),
    | evitando los bloqueos anti-bot (`LOGIN_REQUIRED`) y errores 403 de las
    | instancias públicas de Piped.
    |
    */

    'innertube' => [
        'enabled' => (bool) env('MUSIC_INNERTUBE_ENABLED', true),

        'endpoint' => rtrim((string) env('MUSIC_INNERTUBE_ENDPOINT', 'https://www.youtube.com/youtubei/v1'), '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Listas para la portada
    |--------------------------------------------------------------------------
    |
    | Las sugerencias del día y las novedades salen de la lista pública de
    | canciones más escuchadas de Apple Music para la región indicada (código
    | de país ISO: mx, es, us, ar...). El feed trae la fecha de publicación
    | de cada canción; el audio se sigue resolviendo como en el resto de la app.
    |
    */

    'charts' => [
        'region' => env('MUSIC_CHARTS_REGION', 'mx'),

        // Zona horaria que decide cuándo cambian las sugerencias "del día".
        'timezone' => env('MUSIC_CHARTS_TIMEZONE', 'America/Mexico_City'),

        'endpoint' => rtrim((string) env('MUSIC_CHARTS_ENDPOINT', 'https://rss.marketingtools.apple.com/api/v2'), '/'),

        // Una canción cuenta como novedad si se publicó hace este número de meses o menos.
        'new_release_months' => (int) env('MUSIC_NEW_RELEASE_MONTHS', 2),

        // Segundos que se conserva la lista antes de volver a pedirla. 6 horas.
        'ttl' => (int) env('MUSIC_CHARTS_TTL', 21600),

        /*
         | Géneros de la portada. Cada uno sale de la lista de canciones más
         | populares de ese género en la misma región (feed público de iTunes,
         | que también trae la fecha de publicación y alimenta las novedades).
         | `id` es el identificador de género de Apple; `hue` (0-360) es el
         | tono con el que se tiñe su tarjeta.
         */
        'genre_endpoint' => rtrim((string) env('MUSIC_GENRE_ENDPOINT', 'https://itunes.apple.com'), '/'),

        'genres' => [
            'rock' => ['name' => 'Rock', 'tagline' => 'Guitarras, clásicos y rock en español', 'id' => 21, 'hue' => 8],
            'pop' => ['name' => 'Pop', 'tagline' => 'Los estribillos del momento', 'id' => 14, 'hue' => 328],
            'hip-hop' => ['name' => 'Rap / Hip-Hop', 'tagline' => 'Barras, trap y boom bap', 'id' => 18, 'hue' => 38],
            'reggae' => ['name' => 'Reggae', 'tagline' => 'Roots, ska y dub', 'id' => 24, 'hue' => 142],
            'reggaeton' => ['name' => 'Reguetón', 'tagline' => 'Urbano latino para subir el volumen', 'id' => 1119, 'hue' => 268],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ficha de artista
    |--------------------------------------------------------------------------
    |
    | La foto y las cifras del artista salen de la API pública de Deezer y la
    | biografía de Wikipedia en español. Solo se guardan en caché los datos:
    | las imágenes se cargan desde sus propios servidores.
    |
    */

    'artists' => [
        'deezer_endpoint' => rtrim((string) env('MUSIC_DEEZER_ENDPOINT', 'https://api.deezer.com'), '/'),

        'wikipedia_endpoint' => rtrim((string) env('MUSIC_WIKIPEDIA_ENDPOINT', 'https://es.wikipedia.org'), '/'),

        // Segundos que se conserva la ficha. 7 días.
        'ttl' => (int) env('MUSIC_ARTIST_TTL', 604800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caché
    |--------------------------------------------------------------------------
    */

    'cache' => [
        // Resultados de búsqueda (segundos). 6 horas por defecto.
        'search_ttl' => (int) env('MUSIC_SEARCH_TTL', 21600),

        // Tope para los enlaces de stream (segundos). Nunca se guardan más allá
        // de su propia caducidad (parámetro `expire` de la URL).
        'stream_ttl' => (int) env('MUSIC_STREAM_TTL', 3600),
    ],

];
