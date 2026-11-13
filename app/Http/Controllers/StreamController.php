<?php

namespace App\Http\Controllers;

use App\Exceptions\MusicStreamingException;
use App\Services\MusicStreamingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * Proxy local de audio por rangos (HTTP 206).
 *
 * Los enlaces directos de googlevideo (y los proxies que reenvían cabeceras)
 * validan que el `User-Agent` coincida con el cliente que generó el stream
 * (`c=ANDROID_VR`, `c=IOS`, `c=ANDROID`). Como el elemento `<audio>` del
 * navegador no puede cambiar su `User-Agent`, este endpoint pide fragmentos
 * acotados con la cabecera correcta y los sirve al reproductor.
 */
class StreamController extends Controller
{
    /** Tamaño máximo por petición Range (2 MB) para evitar throttling y no retener workers PHP-FPM. */
    private const CHUNK_SIZE = 2_097_152;

    public function __invoke(
        Request $request,
        MusicStreamingService $service,
        string $videoId,
        int $index = 0,
    ): Response {
        try {
            $stream = $service->getStream($videoId);
        } catch (MusicStreamingException|InvalidArgumentException) {
            abort(502);
        }

        $source = $stream['sources'][$index] ?? $stream['sources'][0] ?? null;

        if (! is_array($source)) {
            abort(404);
        }

        $range = $this->normalizeRange($request->header('Range'));
        $upstreamUrl = (string) ($source['upstream_url'] ?? $source['url'] ?? '');
        $upstream = $upstreamUrl !== '' ? $service->fetchStreamChunk($upstreamUrl, $range) : null;

        // Si el enlace en caché caducó o devuelve 403, renueva una vez en caliente.
        if ($upstream === null || ($upstream->failed() && $upstream->status() !== 416)) {
            try {
                $stream = $service->getStream($videoId, fresh: true);
                $source = $stream['sources'][$index] ?? $stream['sources'][0] ?? null;
                $upstreamUrl = is_array($source) ? (string) ($source['upstream_url'] ?? $source['url'] ?? '') : '';
                $upstream = $upstreamUrl !== '' ? $service->fetchStreamChunk($upstreamUrl, $range) : null;
            } catch (MusicStreamingException|InvalidArgumentException) {
                $upstream = null;
            }
        }

        if ($upstream === null || ($upstream->failed() && $upstream->status() !== 416)) {
            abort(502);
        }

        $body = $upstream->body();
        $headers = [
            'Content-Type' => $upstream->header('Content-Type') ?: (string) ($source['mime_type'] ?? 'audio/webm'),
            'Content-Length' => (string) strlen($body),
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-store',
        ];

        if ($contentRange = $upstream->header('Content-Range')) {
            $headers['Content-Range'] = $contentRange;
        }

        return response($body, $upstream->status(), $headers);
    }

    private function normalizeRange(?string $rangeHeader): string
    {
        if ($rangeHeader !== null && preg_match('/^bytes=(\d+)-(\d*)$/i', trim($rangeHeader), $matches)) {
            $start = (int) $matches[1];
            $maxEnd = $start + self::CHUNK_SIZE - 1;
            $end = $matches[2] !== '' ? min((int) $matches[2], $maxEnd) : $maxEnd;

            if ($end < $start) {
                $end = $maxEnd;
            }

            return "bytes={$start}-{$end}";
        }

        return 'bytes=0-'.(self::CHUNK_SIZE - 1);
    }
}
