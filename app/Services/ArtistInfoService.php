<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ficha de un artista: foto, cifras y biografía.
 *
 * La foto, los seguidores y los artistas parecidos salen de la API pública
 * de Deezer; la biografía, de Wikipedia en español. Ninguna de las dos pide
 * clave. Solo se cachean los datos: las imágenes se cargan desde su origen.
 *
 * Forma de la ficha:
 *   ['name', 'photo_url', 'fans', 'albums', 'summary', 'bio', 'bio_url', 'related' => [['name', 'photo_url']]]
 */
class ArtistInfoService
{
    /** Palabras que delatan que un artículo de Wikipedia habla de música. */
    private const MUSIC_HINTS = [
        'cantante', 'cantautor', 'banda', 'músic', 'music', 'rapero', 'rapera', 'grupo', 'dúo', 'duo',
        'compositor', 'disc jockey', 'dj', 'productor', 'guitarrista', 'pianista', 'baterista', 'vocalista',
        'reguetón', 'reggaet', 'rock', 'pop', 'hip hop', 'trap', 'álbum', 'orquesta', 'intérprete',
    ];

    /** Artistas parecidos que se muestran como máximo. */
    private const RELATED_LIMIT = 8;

    /** Frases de biografía que se piden a Wikipedia. */
    private const BIO_SENTENCES = 5;

    /**
     * Devuelve la ficha del artista. Si ninguna fuente lo conoce, la ficha
     * llega con el nombre tal cual y el resto vacío.
     *
     * @return array{name: string, photo_url: string|null, fans: int|null, albums: int|null, summary: string|null, bio: string|null, bio_url: string|null, related: list<array{name: string, photo_url: string|null}>}
     */
    public function find(string $name): array
    {
        $name = Str::squish($name);
        $key = 'music:artist-info:'.sha1(Str::lower($name));

        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        $profile = $this->deezerProfile($name);
        $article = $this->wikipediaArticle($profile['name'] ?? $name);

        $info = [
            'name' => $profile['name'] ?? $name,
            'photo_url' => $profile['photo_url'] ?? $article['image_url'] ?? null,
            'fans' => $profile['fans'] ?? null,
            'albums' => $profile['albums'] ?? null,
            'summary' => $article['summary'] ?? null,
            'bio' => $article['bio'] ?? null,
            'bio_url' => $article['url'] ?? null,
            'related' => $profile === null ? [] : $this->deezerRelated($profile['id']),
        ];

        // Una ficha vacía puede ser un fallo puntual de las fuentes: no se cachea.
        if ($profile !== null || $article !== null) {
            Cache::put($key, $info, (int) config('music.artists.ttl', 604800));
        }

        return $info;
    }

    /**
     * El artista de Deezer cuyo nombre coincide; entre homónimos, el más seguido.
     *
     * @return array{id: int, name: string, photo_url: string|null, fans: int, albums: int}|null
     */
    private function deezerProfile(string $name): ?array
    {
        $items = $this->get($this->deezerUrl('/search/artist'), ['q' => $name, 'limit' => 10])['data'] ?? null;

        if (! is_array($items)) {
            return null;
        }

        $needle = $this->fold($name);

        $match = collect($items)
            ->filter(fn ($item) => is_array($item) && isset($item['id'], $item['name']))
            ->filter(fn (array $item) => $this->fold((string) $item['name']) === $needle)
            ->sortByDesc(fn (array $item) => (int) ($item['nb_fan'] ?? 0))
            ->first();

        if ($match === null) {
            return null;
        }

        return [
            'id' => (int) $match['id'],
            'name' => (string) $match['name'],
            'photo_url' => $this->photo($match),
            'fans' => (int) ($match['nb_fan'] ?? 0),
            'albums' => (int) ($match['nb_album'] ?? 0),
        ];
    }

    /**
     * @return list<array{name: string, photo_url: string|null}>
     */
    private function deezerRelated(int $artistId): array
    {
        $items = $this->get($this->deezerUrl("/artist/{$artistId}/related"), ['limit' => self::RELATED_LIMIT])['data'] ?? null;

        return collect(is_array($items) ? $items : [])
            ->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null))
            ->map(fn (array $item) => ['name' => (string) $item['name'], 'photo_url' => $this->photo($item, 'picture_medium')])
            ->take(self::RELATED_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Primer artículo de Wikipedia que coincide con el nombre y habla de
     * música (así "Queen" no acaba en la página de una reina).
     *
     * @return array{summary: string|null, bio: string, url: string, image_url: string|null}|null
     */
    private function wikipediaArticle(string $name): ?array
    {
        $endpoint = rtrim((string) config('music.artists.wikipedia_endpoint'), '/');

        $pages = $this->get($endpoint.'/w/api.php', [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'redirects' => 1,
            'generator' => 'search',
            'gsrsearch' => $name,
            'gsrlimit' => 6,
            'prop' => 'extracts|description|pageimages|info',
            'exintro' => 1,
            'explaintext' => 1,
            'exsentences' => self::BIO_SENTENCES,
            'exlimit' => 'max',
            'piprop' => 'original',
            'inprop' => 'url',
        ])['query']['pages'] ?? null;

        if (! is_array($pages)) {
            return null;
        }

        $needle = $this->fold($name);

        $page = collect($pages)
            ->filter(fn ($page) => is_array($page) && filled($page['extract'] ?? null))
            ->sortBy(fn (array $page) => (int) ($page['index'] ?? PHP_INT_MAX))
            ->first(fn (array $page) => Str::contains($this->fold((string) ($page['title'] ?? '')), $needle)
                && $this->looksMusical(($page['description'] ?? '').' '.$page['extract']));

        if ($page === null) {
            return null;
        }

        return [
            'summary' => filled($page['description'] ?? null) ? Str::ucfirst((string) $page['description']) : null,
            'bio' => Str::squish((string) $page['extract']),
            'url' => (string) ($page['fullurl'] ?? $endpoint.'/wiki/'.rawurlencode(str_replace(' ', '_', (string) $page['title']))),
            'image_url' => $this->httpsUrl($page['original']['source'] ?? null),
        ];
    }

    private function looksMusical(string $text): bool
    {
        return Str::contains(Str::lower($text), self::MUSIC_HINTS);
    }

    /**
     * Foto del artista, o null si Deezer solo tiene su silueta genérica
     * (la URL de esas no lleva identificador de imagen: `/artist//`).
     *
     * @param  array<string, mixed>  $artist
     */
    private function photo(array $artist, string $size = 'picture_xl'): ?string
    {
        $url = $this->httpsUrl($artist[$size] ?? null);

        return $url === null || str_contains($url, '/artist//') ? null : $url;
    }

    private function httpsUrl(mixed $url): ?string
    {
        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }

    /**
     * Nombre sin mayúsculas, acentos ni signos, para comparar "Beyoncé" con "beyonce".
     */
    private function fold(string $value): string
    {
        return Str::squish((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))));
    }

    private function deezerUrl(string $path): string
    {
        return rtrim((string) config('music.artists.deezer_endpoint'), '/').$path;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>|null null si la fuente no responde
     */
    private function get(string $url, array $query): ?array
    {
        try {
            $response = $this->http()->get($url, $query);
        } catch (ConnectionException $e) {
            Log::warning("Ficha de artista no disponible en {$url}: {$e->getMessage()}");

            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    private function http(): PendingRequest
    {
        // Wikipedia pide que cada cliente se identifique con su propio User-Agent.
        return Http::connectTimeout(4)
            ->timeout(8)
            ->acceptJson()
            ->withUserAgent(config('app.name').'/1.0 (reproductor de música personal)')
            ->withOptions(config('music.piped.force_ipv4', true) ? ['force_ip_resolve' => 'v4'] : []);
    }
}
