<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Locale;

/**
 * Listas para la portada: sugerencias del día, novedades y géneros.
 *
 * Parte de las listas públicas de canciones más escuchadas de la región
 * (feeds de Apple Music e iTunes, que incluyen la fecha de publicación) y
 * convierte cada entrada en una canción reproducible buscándola en el
 * catálogo con `MusicStreamingService`. No guarda audio: solo cachea metadatos.
 *
 * Forma de una entrada de la lista:
 *   ['id', 'title', 'artist', 'released_at' => 'Y-m-d'|null]
 */
class ChartService
{
    /** Entradas que los feeds admiten como máximo. */
    private const FEED_SIZE = 100;

    /** Canciones de reserva que se resuelven para compensar las excluidas. */
    private const SPARE = 6;

    /** Rondas de búsqueda como máximo al resolver una lista. */
    private const MAX_ROUNDS = 3;

    /** La correspondencia entrada → canción no cambia: se guarda un mes. */
    private const MATCH_TTL = 2_592_000;

    /** Una lista a medias (alguna búsqueda falló) se reintenta pronto. */
    private const PARTIAL_TTL = 600;

    private string $region;

    private string $timezone;

    private int $ttl;

    public function __construct(private MusicStreamingService $music)
    {
        $this->region = Str::lower((string) config('music.charts.region', 'mx'));
        $this->timezone = (string) config('music.charts.timezone', 'America/Mexico_City');
        $this->ttl = (int) config('music.charts.ttl', 21600);
    }

    /**
     * Sugerencias del día: una selección de lo más escuchado que cambia
     * cada día y se mantiene estable durante todo el día.
     *
     * @param  list<string>  $except  IDs de vídeo que no deben aparecer
     * @return list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int, released_at: string|null}>
     */
    public function dailyPicks(int $limit = 12, array $except = []): array
    {
        $day = $this->today()->toDateString();

        $tracks = $this->rememberTracks("daily:{$day}", $limit, fn () => collect($this->entries())
            ->sortBy(fn (array $entry) => crc32($day.'|'.$entry['id']))
            ->values()
            ->all());

        return $this->without($tracks, $except, $limit);
    }

    /**
     * Novedades: canciones publicadas en los últimos meses
     * (`music.charts.new_release_months`), de la más reciente a la más
     * antigua. Se buscan en la lista general y en la de cada género.
     *
     * @param  list<string>  $except  IDs de vídeo que no deben aparecer
     * @return list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int, released_at: string|null}>
     */
    public function newReleases(int $limit = 18, array $except = []): array
    {
        $today = $this->today()->toDateString();
        $since = $this->today()->subMonthsNoOverflow((int) config('music.charts.new_release_months', 2))->toDateString();

        $tracks = $this->rememberTracks("new:{$since}", $limit, fn () => collect($this->entries())
            ->concat(collect($this->genreFeeds(array_keys($this->genres())))->flatten(1))
            ->filter(fn (array $entry) => $entry['released_at'] !== null
                && $entry['released_at'] >= $since
                && $entry['released_at'] <= $today)
            ->unique('id')
            ->unique(fn (array $entry) => Str::lower($entry['title'].'|'.$entry['artist']))
            ->sortByDesc('released_at')
            ->values()
            ->all());

        return $this->without($tracks, $except, $limit);
    }

    /**
     * Las canciones más populares de un género, en el orden de su lista.
     *
     * @param  list<string>  $except  IDs de vídeo que no deben aparecer
     * @return list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int, released_at: string|null}>
     */
    public function genreTracks(string $slug, int $limit = 30, array $except = []): array
    {
        if ($this->genre($slug) === null) {
            return [];
        }

        $tracks = $this->rememberTracks("genre:{$slug}", $limit, fn () => $this->genreFeeds([$slug])[$slug]);

        return $this->without($tracks, $except, $limit);
    }

    /**
     * Géneros disponibles, en el orden en que se muestran.
     *
     * @return array<string, array{name: string, tagline: string, id: int, hue: int}>
     */
    public function genres(): array
    {
        return (array) config('music.charts.genres', []);
    }

    /**
     * @return array{name: string, tagline: string, id: int, hue: int}|null
     */
    public function genre(string $slug): ?array
    {
        return $this->genres()[$slug] ?? null;
    }

    /**
     * Las canciones más escuchadas ahora mismo en la región configurada.
     *
     * @return list<array{id: string, title: string, artist: string, released_at: string|null}>
     */
    public function entries(): array
    {
        $key = "music:chart:{$this->region}:entries";

        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        $entries = $this->fetchEntries();

        // Una lista vacía suele ser un fallo puntual del feed: no se cachea.
        if ($entries !== []) {
            Cache::put($key, $entries, $this->ttl);
        }

        return $entries;
    }

    /**
     * Fecha de salida en corto: "Hoy", "Hace 3 días" o "5 nov".
     */
    public function releaseLabel(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $released = Carbon::parse($date, $this->timezone);
        $days = (int) $released->diffInDays($this->today());

        return match (true) {
            $days <= 0 => 'Hoy',
            $days === 1 => 'Ayer',
            $days <= 6 => "Hace {$days} días",
            default => rtrim($released->locale('es')->isoFormat('D MMM'), '.'),
        };
    }

    /**
     * Nombre legible de la región de la lista ("México").
     */
    public function regionName(): string
    {
        $name = class_exists(Locale::class) ? Locale::getDisplayRegion('-'.Str::upper($this->region), 'es') : '';

        return $name !== '' ? $name : Str::upper($this->region);
    }

    /**
     * @return list<array{id: string, title: string, artist: string, released_at: string|null}>
     */
    private function fetchEntries(): array
    {
        $url = sprintf(
            '%s/%s/music/most-played/%d/songs.json',
            rtrim((string) config('music.charts.endpoint'), '/'),
            $this->region,
            self::FEED_SIZE,
        );

        try {
            // El feed solo contesta por HTTP/2 (por HTTP/1.1 la conexión se queda
            // colgada) y devuelve 502 si se le pide `Accept: application/json`.
            $response = $this->configure(Http::withOptions(['version' => 2.0]))->get($url);
        } catch (ConnectionException $e) {
            Log::warning("Lista de éxitos [{$this->region}] no disponible: {$e->getMessage()}");

            return [];
        }

        if ($response->failed()) {
            Log::warning("Lista de éxitos [{$this->region}] no disponible: HTTP {$response->status()}");

            return [];
        }

        return $this->normalize($response->json('feed.results'), fn (array $item) => [
            'id' => $item['id'] ?? null,
            'title' => $item['name'] ?? null,
            'artist' => $item['artistName'] ?? null,
            'released_at' => $item['releaseDate'] ?? null,
        ]);
    }

    /**
     * Listas de varios géneros a la vez. Las que no están en caché se piden
     * en paralelo; un género cuyo feed falla vale una lista vacía.
     *
     * @param  list<string>  $slugs
     * @return array<string, list<array{id: string, title: string, artist: string, released_at: string|null}>>
     */
    private function genreFeeds(array $slugs): array
    {
        $feeds = [];
        $missing = [];

        foreach ($slugs as $slug) {
            $cached = Cache::get($this->genreKey($slug));

            if (is_array($cached)) {
                $feeds[$slug] = $cached;
            } else {
                $feeds[$slug] = [];
                $missing[] = $slug;
            }
        }

        if ($missing === []) {
            return $feeds;
        }

        $responses = Http::pool(function (Pool $pool) use ($missing): void {
            foreach ($missing as $slug) {
                $this->configure($pool->as($slug))->get($this->genreUrl($slug));
            }
        });

        foreach ($missing as $slug) {
            $response = $responses[$slug] ?? null;

            if (! $response instanceof Response || $response->failed()) {
                Log::warning("Lista del género [{$slug}] no disponible.");

                continue;
            }

            // Con una sola canción el feed entrega un objeto en lugar de una lista.
            $items = $response->json('feed.entry');
            $items = is_array($items) && ! array_is_list($items) ? [$items] : $items;

            $feeds[$slug] = $this->normalize($items, fn (array $item) => [
                'id' => data_get($item, 'id.attributes.im:id'),
                'title' => data_get($item, 'im:name.label'),
                'artist' => data_get($item, 'im:artist.label'),
                'released_at' => data_get($item, 'im:releaseDate.label'),
            ]);

            if ($feeds[$slug] !== []) {
                Cache::put($this->genreKey($slug), $feeds[$slug], $this->ttl);
            }
        }

        return $feeds;
    }

    /**
     * Convierte los elementos de un feed en entradas con la forma común.
     *
     * @param  callable(array<string, mixed>): array{id: mixed, title: mixed, artist: mixed, released_at: mixed}  $fields
     * @return list<array{id: string, title: string, artist: string, released_at: string|null}>
     */
    private function normalize(mixed $items, callable $fields): array
    {
        return collect(is_array($items) ? $items : [])
            ->filter(fn ($item) => is_array($item))
            ->map($fields)
            ->filter(fn (array $entry) => filled($entry['id']) && filled($entry['title']))
            ->map(fn (array $entry) => [
                'id' => (string) $entry['id'],
                'title' => Str::squish((string) $entry['title']),
                'artist' => Str::squish((string) ($entry['artist'] ?? '')),
                'released_at' => $this->parseDate($entry['released_at']),
            ])
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * Resuelve (y cachea) una lista de entradas como canciones reproducibles.
     *
     * @param  callable(): list<array{id: string, title: string, artist: string, released_at: string|null}>  $entries
     * @return list<array<string, mixed>>
     */
    private function rememberTracks(string $name, int $limit, callable $entries): array
    {
        $wanted = $limit + self::SPARE;
        $key = "music:chart:{$this->region}:{$name}:{$wanted}";

        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        [$tracks, $complete] = $this->resolve($entries(), $wanted);

        if ($tracks !== []) {
            Cache::put($key, $tracks, $complete ? $this->ttl : self::PARTIAL_TTL);
        }

        return $tracks;
    }

    /**
     * Busca en el catálogo la canción de cada entrada, por rondas, hasta
     * reunir `$wanted`. La correspondencia de cada entrada se cachea aparte,
     * así que al día siguiente solo se buscan las que entran nuevas.
     *
     * @param  list<array{id: string, title: string, artist: string, released_at: string|null}>  $entries
     * @return array{0: list<array<string, mixed>>, 1: bool} canciones y si todas las búsquedas respondieron
     */
    private function resolve(array $entries, int $wanted): array
    {
        $tracks = [];
        $complete = true;

        foreach (array_slice(array_chunk($entries, max(1, $wanted)), 0, self::MAX_ROUNDS) as $round) {
            if (count($tracks) >= $wanted) {
                break;
            }

            $matches = [];
            $queries = [];

            foreach ($round as $entry) {
                $match = Cache::get($this->matchKey($entry['id']));

                if (is_array($match)) {
                    $matches[$entry['id']] = $match;
                } else {
                    $queries[$entry['id']] = trim($entry['title'].' '.$entry['artist']);
                }
            }

            foreach ($queries === [] ? [] : $this->music->lookupMany($queries) as $id => $track) {
                if ($track === null) {
                    $complete = false;

                    continue;
                }

                Cache::put($this->matchKey((string) $id), $track, self::MATCH_TTL);
                $matches[$id] = $track;
            }

            foreach ($round as $entry) {
                $track = $matches[$entry['id']] ?? null;

                if ($track !== null && ! isset($tracks[$track['video_id']])) {
                    $tracks[$track['video_id']] = [...$track, 'released_at' => $entry['released_at']];
                }
            }
        }

        return [array_slice(array_values($tracks), 0, $wanted), $complete];
    }

    /**
     * @param  list<array<string, mixed>>  $tracks
     * @param  list<string>  $except
     * @return list<array<string, mixed>>
     */
    private function without(array $tracks, array $except, int $limit): array
    {
        return collect($tracks)
            ->reject(fn (array $track) => in_array($track['video_id'], $except, true))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Tiempos de espera y resolución IPv4 comunes a todas las peticiones.
     */
    private function configure(PendingRequest $request): PendingRequest
    {
        return $request
            ->connectTimeout(4)
            ->timeout(8)
            ->withOptions(config('music.piped.force_ipv4', true) ? ['force_ip_resolve' => 'v4'] : []);
    }

    private function genreUrl(string $slug): string
    {
        return sprintf(
            '%s/%s/rss/topsongs/limit=%d/genre=%d/json',
            rtrim((string) config('music.charts.genre_endpoint'), '/'),
            $this->region,
            self::FEED_SIZE,
            (int) ($this->genre($slug)['id'] ?? 0),
        );
    }

    private function genreKey(string $slug): string
    {
        return "music:chart:{$this->region}:genre-entries:{$slug}";
    }

    private function matchKey(string $entryId): string
    {
        return 'music:chart:match:'.sha1($entryId);
    }

    private function parseDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $match) ? $match[0] : null;
    }

    private function today(): Carbon
    {
        return Carbon::now($this->timezone)->startOfDay();
    }
}
