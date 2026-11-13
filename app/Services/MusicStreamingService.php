<?php

namespace App\Services;

use App\Exceptions\MusicStreamingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Servicio de búsqueda y resolución de streams musicales.
 *
 * No aloja ni descarga nada: busca canciones en el catálogo de YouTube Music
 * (vía Piped con respaldo en Innertube), normaliza sus metadatos y resuelve
 * enlaces reproducibles (directamente contra YouTube Innertube desde la IP
 * local o mediante instancias de Piped).
 *
 * Forma de una canción ("track"):
 *   ['video_id', 'title', 'artist', 'thumbnail_url', 'duration']
 *
 * Forma de un stream:
 *   track + ['sources' => [['url', 'mime_type', 'codec', 'bitrate', 'quality', 'kind']],
 *            'expires_at' => timestamp, 'instance' => url]
 */
class MusicStreamingService
{
    /** Filtros de búsqueda de Piped para el catálogo de YouTube Music. */
    public const FILTER_SONGS = 'music_songs';

    public const FILTER_VIDEOS = 'music_videos';

    private const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    /** Margen (s) antes de la caducidad a partir del cual un enlace ya no se reutiliza. */
    private const EXPIRY_MARGIN = 120;

    /** Búsquedas simultáneas que `lookupMany()` lanza contra una misma instancia. */
    private const LOOKUP_CONCURRENCY = 6;

    /** Hosts de portadas que se sirven directamente, sin pasar por el proxy de la instancia. */
    private const THUMBNAIL_HOSTS = ['ytimg.com', 'googleusercontent.com', 'ggpht.com'];

    /**
     * Perfiles de cliente para la API Innertube de YouTube (`/youtubei/v1/player`).
     * Entregan URLs directas sin cifrado JS (`signatureCipher`).
     *
     * @var array<string, array{id: int, user_agent: string, client: array<string, mixed>}>
     */
    private const INNERTUBE_CLIENTS = [
        'ANDROID_VR' => [
            'id' => 28,
            'user_agent' => 'com.google.android.apps.youtube.vr.oculus/1.65.10 (Linux; U; Android 12L; eureka-user Build/SQ3A.220605.009.A1) gzip',
            'client' => [
                'clientName' => 'ANDROID_VR',
                'clientVersion' => '1.65.10',
                'deviceMake' => 'Oculus',
                'deviceModel' => 'Quest 3',
                'androidSdkVersion' => 32,
                'userAgent' => 'com.google.android.apps.youtube.vr.oculus/1.65.10 (Linux; U; Android 12L; eureka-user Build/SQ3A.220605.009.A1) gzip',
                'osName' => 'Android',
                'osVersion' => '12L',
                'hl' => 'en',
                'gl' => 'US',
                'utcOffsetMinutes' => 0,
            ],
        ],
        'IOS' => [
            'id' => 5,
            'user_agent' => 'com.google.ios.youtube/21.26.4 (iPhone16,2; U; CPU iOS 18_3_2 like Mac OS X;)',
            'client' => [
                'clientName' => 'IOS',
                'clientVersion' => '21.26.4',
                'deviceMake' => 'Apple',
                'deviceModel' => 'iPhone16,2',
                'userAgent' => 'com.google.ios.youtube/21.26.4 (iPhone16,2; U; CPU iOS 18_3_2 like Mac OS X;)',
                'osName' => 'iPhone',
                'osVersion' => '18.3.2.22D82',
                'hl' => 'en',
                'gl' => 'US',
                'utcOffsetMinutes' => 0,
            ],
        ],
        'ANDROID' => [
            'id' => 3,
            'user_agent' => 'com.google.android.youtube/21.26.364 (Linux; U; Android 11) gzip',
            'client' => [
                'clientName' => 'ANDROID',
                'clientVersion' => '21.26.364',
                'androidSdkVersion' => 30,
                'userAgent' => 'com.google.android.youtube/21.26.364 (Linux; U; Android 11) gzip',
                'osName' => 'Android',
                'osVersion' => '11',
                'hl' => 'en',
                'gl' => 'US',
                'utcOffsetMinutes' => 0,
            ],
        ],
    ];

    /** @var list<string> */
    private array $instances;

    private int $timeout;

    private int $cooldown;

    private bool $verifyStream;

    private bool $forceIpv4;

    private bool $innertubeEnabled;

    private string $innertubeEndpoint;

    private int $searchTtl;

    private int $streamTtl;

    private int $searchLimit;

    public function __construct()
    {
        $this->instances = config('music.piped.instances', []);
        $this->timeout = config('music.piped.timeout', 8);
        $this->cooldown = config('music.piped.cooldown', 300);
        $this->verifyStream = config('music.piped.verify_stream', true);
        $this->forceIpv4 = config('music.piped.force_ipv4', true);
        $this->innertubeEnabled = (bool) config('music.innertube.enabled', true);
        $this->innertubeEndpoint = rtrim((string) config('music.innertube.endpoint', 'https://www.youtube.com/youtubei/v1'), '/');
        $this->searchTtl = config('music.cache.search_ttl', 21600);
        $this->streamTtl = config('music.cache.stream_ttl', 3600);
        $this->searchLimit = max(20, min(100, (int) config('music.piped.search_limit', 80)));
    }

    /**
     * Busca canciones (hasta `search_limit`, 80 por defecto). Los resultados se guardan en caché.
     *
     * @return list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}>
     *
     * @throws MusicStreamingException si ninguna instancia responde.
     */
    public function search(string $query, string $filter = self::FILTER_SONGS): array
    {
        $query = Str::squish($query);

        if ($query === '') {
            return [];
        }

        $key = 'music:search:'.$filter.':'.$this->searchLimit.':'.sha1(Str::lower($query));

        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $tracks = $this->attempt('search', fn (string $instance) => $this->fetchSearchPages($instance, $query, $filter));
        } catch (MusicStreamingException $e) {
            if (! $this->innertubeEnabled) {
                throw $e;
            }

            $tracks = $this->searchViaInnertube($query, $e->attempts);
        }

        if ($this->innertubeEnabled && count($tracks) > 0 && count($tracks) < 50) {
            $tracks = $this->supplementSearchResults($query, $filter, $tracks);
        }

        // Un resultado vacío puede ser un fallo puntual de la instancia: no se cachea.
        if ($tracks !== []) {
            Cache::put($key, $tracks, $this->searchTtl);
        }

        return $tracks;
    }

    /**
     * Obtiene el catálogo de canciones de un artista (entre 50 y 100 resultados),
     * priorizando las pistas cuyo autor coincide con el artista.
     *
     * @return list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}>
     *
     * @throws MusicStreamingException si ninguna instancia responde.
     */
    public function artistTracks(string $artist): array
    {
        $artist = Str::squish($artist);

        if ($artist === '') {
            return [];
        }

        $key = 'music:artist:'.$this->searchLimit.':'.sha1(Str::lower($artist));

        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        $tracks = collect($this->search($artist, self::FILTER_SONGS));

        if ($this->innertubeEnabled && $tracks->count() < 60) {
            try {
                $more = $this->search($artist.' audio', self::FILTER_VIDEOS);
                $tracks = $tracks->concat($more)->unique('video_id')->values();
            } catch (MusicStreamingException) {
                // Nos quedamos con las canciones ya obtenidas.
            }
        }

        $needle = Str::lower($artist);
        [$exact, $related] = $tracks->partition(
            fn (array $track) => Str::contains(Str::lower($track['artist']), $needle)
                || Str::contains($needle, Str::lower($track['artist']))
        );

        $result = $exact->concat($related)->take($this->searchLimit)->values()->all();

        if ($result !== []) {
            Cache::put($key, $result, $this->searchTtl);
        }

        return $result;
    }

    /**
     * Devuelve la primera canción del catálogo para cada consulta ("título artista").
     *
     * Está pensado para convertir listas externas (éxitos, novedades) en
     * canciones reproducibles: lanza las búsquedas en paralelo contra una
     * instancia de Piped y, las que queden sin respuesta, contra la siguiente.
     * Una consulta sin resultado (o sin instancia que conteste) vale null.
     *
     * @param  array<array-key, string>  $queries  clave propia => texto a buscar
     * @return array<array-key, array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}|null>
     */
    public function lookupMany(array $queries): array
    {
        $found = array_fill_keys(array_keys($queries), null);
        $pending = array_filter(array_map(fn (string $query) => Str::squish($query), $queries));

        foreach ($this->candidates('search', null) as $instance) {
            if ($pending === []) {
                break;
            }

            $responses = Http::pool(function (Pool $pool) use ($instance, $pending): void {
                foreach ($pending as $key => $query) {
                    $this->configure($pool->as((string) $key))
                        ->acceptJson()
                        ->get($instance.'/search', ['q' => $query, 'filter' => self::FILTER_SONGS]);
                }
            }, self::LOOKUP_CONCURRENCY);

            $answered = 0;

            foreach ($responses as $key => $response) {
                if (! $response instanceof Response || $response->failed() || ! is_array($response->json('items'))) {
                    continue;
                }

                $answered++;
                $found[$key] = collect($response->json('items'))
                    ->filter(fn ($item) => is_array($item) && ($item['type'] ?? 'stream') === 'stream')
                    ->map(fn (array $item) => $this->mapSearchItem($item))
                    ->first(fn (?array $track) => $track !== null);

                unset($pending[$key]);
            }

            if ($answered === 0) {
                Cache::put($this->cooldownKey('search', $instance), true, $this->cooldown);
                Log::warning("Piped [lookup] no respondió en {$instance}.");
            }
        }

        return $found;
    }

    /**
     * Resuelve el stream de audio de un vídeo.
     *
     * Con $fresh = true se ignora la caché y se evita, si es posible, la instancia
     * que generó el enlace anterior: es lo que pide el reproductor cuando una URL
     * falla o caduca.
     *
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int, sources: list<array<string, mixed>>, expires_at: int, instance: string}
     *
     * @throws MusicStreamingException si ninguna instancia entrega un stream reproducible.
     */
    public function getStream(string $videoId, bool $fresh = false): array
    {
        if (! preg_match(self::VIDEO_ID_PATTERN, $videoId)) {
            throw new InvalidArgumentException("ID de vídeo no válido: [{$videoId}].");
        }

        $key = 'music:stream:'.$videoId;
        $cached = Cache::get($key);

        if (! $fresh && is_array($cached) && time() < $cached['expires_at'] - self::EXPIRY_MARGIN) {
            return $cached;
        }

        $stream = $this->resolveWithFallback(
            $videoId,
            avoid: $fresh && is_array($cached) ? ($cached['instance'] ?? null) : null,
        );

        $ttl = min($this->streamTtl, $stream['expires_at'] - time() - self::EXPIRY_MARGIN);

        if ($ttl > 0) {
            Cache::put($key, $stream, $ttl);
        } else {
            Cache::forget($key);
        }

        return $stream;
    }

    /**
     * Descarga un fragmento por rango (`Range: bytes=start-end`) con el `User-Agent`
     * que exige el parámetro `c=` del enlace de googlevideo.
     */
    public function fetchStreamChunk(string $url, string $range): ?Response
    {
        try {
            return $this->http()
                ->timeout(max($this->timeout, 15))
                ->withHeaders([
                    'User-Agent' => $this->userAgentForUrl($url),
                    'Range' => $range,
                ])
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * Devuelve el `User-Agent` que corresponde al cliente (`c=`) codificado en la URL.
     */
    public function userAgentForUrl(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $client = strtoupper((string) ($query['c'] ?? ''));

        return match ($client) {
            'ANDROID_VR' => self::INNERTUBE_CLIENTS['ANDROID_VR']['user_agent'],
            'IOS' => self::INNERTUBE_CLIENTS['IOS']['user_agent'],
            'ANDROID' => self::INNERTUBE_CLIENTS['ANDROID']['user_agent'],
            default => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        };
    }

    /**
     * Devuelve una portada de googleusercontent al tamaño pedido (las de ytimg no cambian).
     */
    public static function thumbnail(?string $url, int $size = 544): string
    {
        return (string) preg_replace('/=w\d+-h\d+/', "=w{$size}-h{$size}", (string) $url, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolución de streams (Innertube + Piped)
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function resolveWithFallback(string $videoId, ?string $avoid = null): array
    {
        $innertubeAttempts = [];

        if ($this->innertubeEnabled) {
            try {
                return $this->resolveInnertubeStream($videoId, $avoid);
            } catch (MusicStreamingException $e) {
                $innertubeAttempts = $e->attempts;

                if ($this->instances === []) {
                    throw $e;
                }
            }
        }

        try {
            return $this->attempt(
                'streams',
                fn (string $instance) => $this->resolveStream($instance, $videoId),
                avoid: $avoid,
            );
        } catch (MusicStreamingException $e) {
            throw new MusicStreamingException(
                $e->getMessage(),
                array_merge($innertubeAttempts, $e->attempts),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveInnertubeStream(string $videoId, ?string $avoid = null): array
    {
        $attempts = [];

        foreach ($this->innertubeCandidates($avoid) as $name) {
            $tag = "innertube:{$name}";
            $profile = self::INNERTUBE_CLIENTS[$name];

            try {
                $data = $this->fetchInnertubePlayer($profile, $videoId);

                return $this->buildStream($tag, $videoId, $data);
            } catch (MusicStreamingException|ConnectionException $e) {
                $attempts[$tag] = $e->getMessage();
                Cache::put($this->cooldownKey('innertube', $tag), true, $this->cooldown);

                Log::warning("Innertube [{$name}] falló en {$videoId}: {$e->getMessage()}");
            }
        }

        throw new MusicStreamingException(
            'Ningún perfil de Innertube pudo resolver el stream.',
            $attempts,
        );
    }

    /**
     * @return list<string>
     */
    private function innertubeCandidates(?string $avoid): array
    {
        [$resting, $healthy] = collect(array_keys(self::INNERTUBE_CLIENTS))
            ->partition(fn (string $name) => Cache::has($this->cooldownKey('innertube', "innertube:{$name}")));

        return $healthy->concat($resting)
            ->sortBy(fn (string $name) => "innertube:{$name}" === $avoid ? 1 : 0)
            ->values()
            ->all();
    }

    /**
     * @param  array{id: int, user_agent: string, client: array<string, mixed>}  $profile
     * @return array<string, mixed>
     */
    private function fetchInnertubePlayer(array $profile, string $videoId): array
    {
        $response = $this->http()
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => $profile['user_agent'],
                'X-YouTube-Client-Name' => (string) $profile['id'],
                'X-YouTube-Client-Version' => (string) $profile['client']['clientVersion'],
                'Origin' => 'https://www.youtube.com',
            ])
            ->post($this->innertubeEndpoint.'/player?prettyPrint=false', [
                'videoId' => $videoId,
                'context' => ['client' => $profile['client']],
                'contentCheckOk' => true,
                'racyCheckOk' => true,
            ]);

        if ($response->failed()) {
            throw new MusicStreamingException("HTTP {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new MusicStreamingException('Respuesta no válida (no es JSON).');
        }

        $status = (string) ($data['playabilityStatus']['status'] ?? '');

        if ($status !== 'OK') {
            $reason = (string) ($data['playabilityStatus']['reason'] ?? ($status ?: 'Stream no disponible.'));

            throw new MusicStreamingException(Str::limit(Str::before($reason, "\n"), 180));
        }

        return $this->normalizeInnertubePayload($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeInnertubePayload(array $data): array
    {
        $details = is_array($data['videoDetails'] ?? null) ? $data['videoDetails'] : [];
        $streaming = is_array($data['streamingData'] ?? null) ? $data['streamingData'] : [];
        $thumbnails = $details['thumbnail']['thumbnails'] ?? [];
        $lastThumb = is_array($thumbnails) && $thumbnails !== [] ? end($thumbnails) : null;

        $audioStreams = collect($streaming['adaptiveFormats'] ?? [])
            ->filter(fn ($f) => is_array($f)
                && filled($f['url'] ?? null)
                && str_starts_with((string) ($f['mimeType'] ?? ''), 'audio/'))
            ->map(function (array $f): array {
                [$mimeType, $codec] = $this->splitMimeType((string) ($f['mimeType'] ?? ''));
                $bitrate = (int) ($f['averageBitrate'] ?? $f['bitrate'] ?? 0);
                $isDefault = $f['audioTrack']['audioIsDefault'] ?? true;

                return [
                    'url' => (string) $f['url'],
                    'mimeType' => $mimeType,
                    'codec' => $codec,
                    'bitrate' => $bitrate,
                    'quality' => $bitrate > 0 ? round($bitrate / 1000).' kbps' : '',
                    'audioTrackType' => $isDefault === false ? 'DUBBED' : null,
                ];
            })
            ->values()
            ->all();

        $videoStreams = collect($streaming['formats'] ?? [])
            ->filter(fn ($f) => is_array($f) && filled($f['url'] ?? null))
            ->map(function (array $f): array {
                [$mimeType] = $this->splitMimeType((string) ($f['mimeType'] ?? ''));

                return [
                    'url' => (string) $f['url'],
                    'mimeType' => $mimeType,
                    'videoOnly' => false,
                    'itag' => (int) ($f['itag'] ?? 18),
                    'bitrate' => (int) ($f['averageBitrate'] ?? $f['bitrate'] ?? 0),
                    'quality' => (string) ($f['qualityLabel'] ?? $f['quality'] ?? ''),
                ];
            })
            ->values()
            ->all();

        return [
            'title' => (string) ($details['title'] ?? ''),
            'uploader' => (string) ($details['author'] ?? ''),
            'duration' => (int) ($details['lengthSeconds'] ?? 0),
            'thumbnailUrl' => is_array($lastThumb) ? ($lastThumb['url'] ?? null) : null,
            'livestream' => (bool) ($details['isLiveContent'] ?? false),
            'audioStreams' => $audioStreams,
            'videoStreams' => $videoStreams,
        ];
    }

    /**
     * @param  array<string, string>  $previousAttempts
     * @return list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}>
     */
    private function searchViaInnertube(string $query, array $previousAttempts = []): array
    {
        $context = [
            'client' => [
                'clientName' => 'WEB',
                'clientVersion' => '2.20250101.00.00',
                'hl' => 'es',
                'gl' => 'US',
            ],
        ];

        try {
            $response = $this->http()
                ->acceptJson()
                ->withHeaders(['Origin' => 'https://www.youtube.com'])
                ->post($this->innertubeEndpoint.'/search?prettyPrint=false', [
                    'query' => $query,
                    'context' => $context,
                ]);

            if ($response->failed() || ! is_array($data = $response->json())) {
                throw new MusicStreamingException("HTTP {$response->status()}");
            }

            $tracks = collect($this->extractVideoRenderers($data))
                ->map(fn (array $renderer) => $this->mapInnertubeSearchItem($renderer))
                ->filter();

            $continuation = $this->extractContinuationToken($data);
            $pages = 1;

            while ($continuation !== null && $tracks->unique('video_id')->count() < $this->searchLimit && $pages < 4) {
                try {
                    $nextResponse = $this->http()
                        ->acceptJson()
                        ->withHeaders(['Origin' => 'https://www.youtube.com'])
                        ->post($this->innertubeEndpoint.'/search?prettyPrint=false', [
                            'continuation' => $continuation,
                            'context' => $context,
                        ]);

                    if ($nextResponse->failed() || ! is_array($nextData = $nextResponse->json())) {
                        break;
                    }

                    $more = collect($this->extractVideoRenderers($nextData))
                        ->map(fn (array $renderer) => $this->mapInnertubeSearchItem($renderer))
                        ->filter();

                    if ($more->isEmpty()) {
                        break;
                    }

                    $tracks = $tracks->concat($more);
                    $continuation = $this->extractContinuationToken($nextData);
                    $pages++;
                } catch (ConnectionException) {
                    break;
                }
            }

            return $tracks
                ->unique('video_id')
                ->take($this->searchLimit)
                ->values()
                ->all();
        } catch (MusicStreamingException|ConnectionException $e) {
            $attempts = array_merge($previousAttempts, ['innertube:WEB' => $e->getMessage()]);

            throw new MusicStreamingException(
                'Ninguna instancia pudo atender la búsqueda.',
                $attempts,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function extractContinuationToken(array $node): ?string
    {
        if (isset($node['continuationCommand']['token']) && is_string($node['continuationCommand']['token'])) {
            return $node['continuationCommand']['token'];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $token = $this->extractContinuationToken($value);

                if ($token !== null) {
                    return $token;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private function extractVideoRenderers(array $node): array
    {
        $renderers = [];

        if (isset($node['videoRenderer']) && is_array($node['videoRenderer'])) {
            $renderers[] = $node['videoRenderer'];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                foreach ($this->extractVideoRenderers($value) as $child) {
                    $renderers[] = $child;
                }
            }
        }

        return $renderers;
    }

    /**
     * @param  array<string, mixed>  $renderer
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}|null
     */
    private function mapInnertubeSearchItem(array $renderer): ?array
    {
        $videoId = $renderer['videoId'] ?? null;
        $durationText = (string) ($renderer['lengthText']['simpleText'] ?? '');

        if (! is_string($videoId) || ! preg_match(self::VIDEO_ID_PATTERN, $videoId) || $durationText === '') {
            return null;
        }

        $title = (string) ($renderer['title']['runs'][0]['text'] ?? $renderer['title']['simpleText'] ?? '');
        $artist = (string) ($renderer['ownerText']['runs'][0]['text'] ?? $renderer['longBylineText']['runs'][0]['text'] ?? '');
        $thumbnails = $renderer['thumbnail']['thumbnails'] ?? [];
        $lastThumb = is_array($thumbnails) && $thumbnails !== [] ? end($thumbnails) : null;

        return [
            'video_id' => $videoId,
            'title' => $title,
            'artist' => $this->cleanArtist($artist),
            'thumbnail_url' => $this->normalizeThumbnail(is_array($lastThumb) ? ($lastThumb['url'] ?? null) : null, $videoId),
            'duration' => $this->parseDuration($durationText),
        ];
    }

    private function parseDuration(string $text): int
    {
        $parts = array_map('intval', explode(':', trim($text)));

        return match (count($parts)) {
            3 => $parts[0] * 3600 + $parts[1] * 60 + $parts[2],
            2 => $parts[0] * 60 + $parts[1],
            default => max(0, $parts[0] ?? 0),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Piped
    |--------------------------------------------------------------------------
    */

    /**
     * Consulta la primera página de `/search` y sigue los enlaces `nextpage`
     * de Piped (`/nextpage/search`) hasta alcanzar `$this->searchLimit`.
     *
     * @return list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}>
     */
    private function fetchSearchPages(string $instance, string $query, string $filter): array
    {
        $data = $this->fetch($instance, '/search', [
            'q' => $query,
            'filter' => $filter,
        ]);

        $tracks = collect($data['items'] ?? [])
            ->filter(fn ($item) => is_array($item) && ($item['type'] ?? 'stream') === 'stream')
            ->map(fn (array $item) => $this->mapSearchItem($item))
            ->filter();

        $nextpage = $data['nextpage'] ?? null;
        $pages = 1;

        while (is_string($nextpage) && $nextpage !== '' && $tracks->unique('video_id')->count() < $this->searchLimit && $pages < 5) {
            try {
                $nextData = $this->fetch($instance, '/nextpage/search', [
                    'nextpage' => $nextpage,
                    'q' => $query,
                    'filter' => $filter,
                ]);
            } catch (MusicStreamingException|ConnectionException) {
                break;
            }

            $pageItems = collect($nextData['items'] ?? [])
                ->filter(fn ($item) => is_array($item) && ($item['type'] ?? 'stream') === 'stream')
                ->map(fn (array $item) => $this->mapSearchItem($item))
                ->filter();

            if ($pageItems->isEmpty()) {
                break;
            }

            $tracks = $tracks->concat($pageItems);
            $nextpage = $nextData['nextpage'] ?? null;
            $pages++;
        }

        return $tracks
            ->unique('video_id')
            ->take($this->searchLimit)
            ->values()
            ->all();
    }

    /**
     * Si una instancia de Piped devuelve menos de 50 resultados (p. ej. 20 sin `nextpage`),
     * amplía el listado con Innertube y vídeos musicales hasta alcanzar `search_limit`.
     *
     * @param  list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}>  $tracks
     * @return list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}>
     */
    private function supplementSearchResults(string $query, string $filter, array $tracks): array
    {
        $merged = collect($tracks);

        try {
            $merged = $merged->concat($this->searchViaInnertube($query))->unique('video_id');
        } catch (MusicStreamingException) {
            // Conserva los resultados principales si el complemento falla.
        }

        if ($merged->count() < 50 && $filter === self::FILTER_SONGS) {
            try {
                $videos = $this->attempt('search', fn (string $instance) => $this->fetchSearchPages($instance, $query, self::FILTER_VIDEOS));
                $merged = $merged->concat($videos)->unique('video_id');
            } catch (MusicStreamingException) {
                // Ignora fallos secundarios.
            }
        }

        return $merged
            ->take($this->searchLimit)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveStream(string $instance, string $videoId): array
    {
        $data = $this->fetch($instance, '/streams/'.$videoId);

        return $this->buildStream($instance, $videoId, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildStream(string $instance, string $videoId, array $data): array
    {
        if ($data['livestream'] ?? false) {
            throw new MusicStreamingException('Es una emisión en directo.');
        }

        $sources = $this->playableSources($data);

        if ($sources === []) {
            throw new MusicStreamingException('La instancia no devolvió ningún stream de audio.');
        }

        if ($this->verifyStream) {
            $sources = $this->dropDeadSources($sources);
        }

        $expiresAt = $this->expiresAt($sources[0]['url']);
        $sources = $this->attachProxyUrls($videoId, $sources, $expiresAt);

        return [
            'video_id' => $videoId,
            'title' => (string) ($data['title'] ?? ''),
            'artist' => $this->cleanArtist($data['uploader'] ?? ''),
            'thumbnail_url' => $this->normalizeThumbnail($data['thumbnailUrl'] ?? null, $videoId),
            'duration' => (int) ($data['duration'] ?? 0),
            'sources' => $sources,
            'expires_at' => $expiresAt,
            'instance' => $instance,
        ];
    }

    /**
     * Ordena las fuentes de mejor a peor: primero solo-audio por bitrate
     * (Opus ~160 kbps, AAC ~128 kbps) y, como último recurso, los MP4 con
     * audio + vídeo, que el elemento <audio> también reproduce.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function playableSources(array $data): array
    {
        $audio = collect($data['audioStreams'] ?? [])
            ->filter(fn ($s) => is_array($s)
                && filled($s['url'] ?? null)
                && str_starts_with((string) ($s['mimeType'] ?? ''), 'audio/'));

        // Si hay pistas dobladas o con audiodescripción, quédate con la original.
        $original = $audio->reject(
            fn (array $s) => in_array($s['audioTrackType'] ?? null, ['DUBBED', 'DESCRIPTIVE', 'SECONDARY'], true)
        );

        $audio = ($original->isNotEmpty() ? $original : $audio)
            ->sortByDesc(fn (array $s) => (int) ($s['bitrate'] ?? 0))
            ->map(fn (array $s) => $this->mapSource($s, 'audio'));

        $muxed = collect($data['videoStreams'] ?? [])
            ->filter(fn ($s) => is_array($s)
                && filled($s['url'] ?? null)
                && ($s['videoOnly'] ?? true) === false
                && ($s['mimeType'] ?? null) === 'video/mp4'
                && (int) ($s['itag'] ?? -1) > 0) // descarta espejos LBRY/HLS
            ->map(fn (array $s) => $this->mapSource($s, 'muxed'));

        return $audio->concat($muxed)->values()->all();
    }

    /**
     * Descarta las fuentes que no responden hasta dar con la primera viva.
     * Las siguientes se dejan sin comprobar como alternativa de códec.
     *
     * @param  list<array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    private function dropDeadSources(array $sources): array
    {
        foreach ($sources as $index => $source) {
            if ($this->isReachable($source['url'])) {
                return array_slice($sources, $index);
            }
        }

        throw new MusicStreamingException('Los enlaces de stream no responden (403/caducados).');
    }

    private function isReachable(string $url): bool
    {
        try {
            return $this->http()
                ->withHeaders([
                    'User-Agent' => $this->userAgentForUrl($url),
                    'Range' => 'bytes=0-1',
                ])
                ->get($url)
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Envuelve con el proxy local (`/stream/{videoId}/{index}`) aquellos enlaces
     * que exigen un `User-Agent` de cliente móvil/VR (googlevideo o parámetros
     * `c=ANDROID*` / `c=IOS`), evitando el 403 del elemento `<audio>`.
     *
     * La URL del proxy va firmada y caduca con el propio stream: el proxy no
     * usa sesión, así que la firma es lo que impide usarlo sin haber entrado.
     *
     * @param  list<array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    private function attachProxyUrls(string $videoId, array $sources, int $expiresAt): array
    {
        foreach ($sources as $index => &$source) {
            if ($this->requiresProxy($source['url'])) {
                $source['upstream_url'] = $source['url'];
                $source['url'] = URL::temporarySignedRoute('stream', Carbon::createFromTimestamp($expiresAt), [
                    'videoId' => $videoId,
                    'index' => $index,
                ], absolute: false);
            }
        }

        return $sources;
    }

    private function requiresProxy(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === 'googlevideo.com' || str_ends_with($host, '.googlevideo.com')) {
            return true;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $client = strtoupper((string) ($query['c'] ?? ''));

        return in_array($client, ['ANDROID', 'ANDROID_VR', 'IOS'], true);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function fetch(string $instance, string $path, array $query = []): array
    {
        $response = $this->client($instance)->get($path, $query);
        $data = $response->json();

        // Piped responde con 500 + {"error": "<stacktrace>", "message": "..."}.
        if (is_array($data) && isset($data['error'])) {
            $reason = (string) ($data['message'] ?? $data['error']);

            throw new MusicStreamingException(Str::limit(Str::before($reason, "\n"), 180));
        }

        if ($response->failed()) {
            throw new MusicStreamingException("HTTP {$response->status()}");
        }

        if (! is_array($data)) {
            throw new MusicStreamingException('Respuesta no válida (no es JSON).');
        }

        return $data;
    }

    private function client(string $instance): PendingRequest
    {
        return $this->http()->baseUrl($instance)->acceptJson();
    }

    /**
     * Base de todas las peticiones salientes.
     *
     * `force_ip_resolve => v4` no es una preferencia de red: con PHP-FPM en
     * macOS (Herd/Valet) la resolución DNS dual IPv4+IPv6 de curl puede
     * abortar el worker y nginx responde 502. Resolver solo IPv4 lo evita.
     */
    private function http(): PendingRequest
    {
        return $this->configure(Http::connectTimeout(4));
    }

    /**
     * Aplica tiempos de espera y resolución IPv4 a una petición ya creada
     * (las de `Http::pool()` no pasan por `http()`).
     */
    private function configure(PendingRequest $request): PendingRequest
    {
        return $request
            ->connectTimeout(4)
            ->timeout($this->timeout)
            ->withOptions($this->forceIpv4 ? ['force_ip_resolve' => 'v4'] : []);
    }

    /*
    |--------------------------------------------------------------------------
    | Failover entre instancias
    |--------------------------------------------------------------------------
    */

    /**
     * Ejecuta $callback contra cada instancia hasta que una funcione.
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    private function attempt(string $scope, callable $callback, ?string $avoid = null): mixed
    {
        $attempts = [];

        foreach ($this->candidates($scope, $avoid) as $instance) {
            try {
                return $callback($instance);
            } catch (MusicStreamingException|ConnectionException $e) {
                $attempts[$instance] = $e->getMessage();
                Cache::put($this->cooldownKey($scope, $instance), true, $this->cooldown);

                Log::warning("Piped [{$scope}] falló en {$instance}: {$e->getMessage()}");
            }
        }

        throw new MusicStreamingException(
            $attempts === []
                ? 'No hay instancias de Piped configuradas (PIPED_INSTANCES).'
                : 'Ninguna instancia de Piped pudo atender la petición.',
            $attempts,
        );
    }

    /**
     * Instancias sanas primero; las que están en reposo quedan como último
     * recurso y la que se quiere evitar, al final del todo.
     *
     * @return list<string>
     */
    private function candidates(string $scope, ?string $avoid): array
    {
        [$resting, $healthy] = collect($this->instances)
            ->partition(fn (string $instance) => Cache::has($this->cooldownKey($scope, $instance)));

        return $healthy->concat($resting)
            ->sortBy(fn (string $instance) => $instance === $avoid ? 1 : 0)
            ->values()
            ->all();
    }

    private function cooldownKey(string $scope, string $instance): string
    {
        return "music:cooldown:{$scope}:".sha1($instance);
    }

    /*
    |--------------------------------------------------------------------------
    | Normalización
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitMimeType(string $raw): array
    {
        if (preg_match('/^([^;]+)(?:;\s*codecs="([^"]+)")?/i', trim($raw), $matches)) {
            return [trim($matches[1]), isset($matches[2]) && $matches[2] !== '' ? trim($matches[2]) : null];
        }

        return [trim($raw), null];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function mapSearchItem(array $item): ?array
    {
        parse_str((string) parse_url((string) ($item['url'] ?? ''), PHP_URL_QUERY), $params);
        $videoId = $params['v'] ?? null;

        if (! is_string($videoId) || ! preg_match(self::VIDEO_ID_PATTERN, $videoId)) {
            return null;
        }

        return [
            'video_id' => $videoId,
            'title' => (string) ($item['title'] ?? ''),
            'artist' => $this->cleanArtist($item['uploaderName'] ?? ''),
            'thumbnail_url' => $this->normalizeThumbnail($item['thumbnail'] ?? null, $videoId),
            'duration' => max(0, (int) ($item['duration'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $stream
     * @return array<string, mixed>
     */
    private function mapSource(array $stream, string $kind): array
    {
        return [
            'url' => (string) $stream['url'],
            'mime_type' => (string) ($stream['mimeType'] ?? ''),
            'codec' => $stream['codec'] ?? null,
            'bitrate' => (int) ($stream['bitrate'] ?? 0),
            'quality' => (string) ($stream['quality'] ?? ''),
            'kind' => $kind,
        ];
    }

    private function cleanArtist(mixed $name): string
    {
        return Str::of((string) $name)->replaceEnd(' - Topic', '')->squish()->toString();
    }

    /**
     * Piped devuelve las portadas a través del proxy de cada instancia
     * (https://proxy.ejemplo/ruta?host=yt3.googleusercontent.com). Se deshace
     * ese rodeo para guardar una URL estable que siga funcionando aunque la
     * instancia desaparezca, y se pide en buena resolución.
     */
    private function normalizeThumbnail(?string $url, string $videoId): string
    {
        $fallback = "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";

        if (blank($url)) {
            return $fallback;
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $host = $query['host'] ?? null;

        $trusted = is_string($host) && collect(self::THUMBNAIL_HOSTS)
            ->contains(fn (string $domain) => $host === $domain || str_ends_with($host, '.'.$domain));

        if ($trusted && isset($parts['path'])) {
            unset($query['host'], $query['qhash']);

            $url = "https://{$host}{$parts['path']}".($query ? '?'.http_build_query($query) : '');
        }

        return self::thumbnail($url);
    }

    /**
     * Los enlaces de googlevideo llevan su caducidad en el parámetro `expire`.
     */
    private function expiresAt(string $url): int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $expire = (int) ($query['expire'] ?? 0);

        return $expire > time() ? $expire : time() + $this->streamTtl;
    }
}
