<?php

namespace App\Http\Controllers;

use App\Exceptions\MusicStreamingException;
use App\Services\MusicStreamingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga directa de una canción al dispositivo del usuario.
 *
 * A diferencia del modo "Guardar sin internet (en la app)" —que almacena el
 * audio en IndexedDB dentro del navegador—, este endpoint entrega el archivo
 * con `Content-Disposition: attachment` para guardarlo en la carpeta de
 * descargas del sistema operativo.
 */
class DownloadController extends Controller
{
    private const CHUNK_SIZE = 2_097_152;

    public function __invoke(
        Request $request,
        MusicStreamingService $service,
        string $videoId,
    ): StreamedResponse {
        try {
            $stream = $service->getStream($videoId);
        } catch (MusicStreamingException|InvalidArgumentException) {
            abort(502);
        }

        $source = $stream['sources'][0] ?? null;

        if (! is_array($source)) {
            abort(404);
        }

        $upstreamUrl = (string) ($source['upstream_url'] ?? $source['url'] ?? '');

        if ($upstreamUrl === '') {
            abort(404);
        }

        $mimeType = (string) ($source['mime_type'] ?? 'audio/webm');
        $extension = match (true) {
            str_contains($mimeType, 'mp4') && ($source['kind'] ?? 'audio') === 'audio' => 'm4a',
            str_contains($mimeType, 'mp4') => 'mp4',
            default => 'webm',
        };

        $title = trim((string) ($request->query('title') ?: $stream['title'] ?: $videoId));
        $artist = trim((string) ($request->query('artist') ?: $stream['artist'] ?: ''));
        $rawName = $artist !== '' ? "{$artist} - {$title}" : $title;
        $safeName = trim((string) preg_replace('/[\/\\\\:*?"<>|]+/', ' ', $rawName));
        $filename = ($safeName !== '' ? Str::limit($safeName, 110, '') : $videoId).'.'.$extension;

        return response()->streamDownload(function () use ($service, $upstreamUrl, $videoId): void {
            $start = 0;
            $total = null;
            $url = $upstreamUrl;

            while ($total === null || $start < $total) {
                $end = $start + self::CHUNK_SIZE - 1;
                if ($total !== null) {
                    $end = min($end, $total - 1);
                }

                $response = $service->fetchStreamChunk($url, "bytes={$start}-{$end}");

                if (($response === null || $response->failed()) && $start === 0) {
                    try {
                        $fresh = $service->getStream($videoId, fresh: true);
                        $freshSource = $fresh['sources'][0] ?? null;
                        $url = is_array($freshSource) ? (string) ($freshSource['upstream_url'] ?? $freshSource['url'] ?? '') : '';
                        $response = $url !== '' ? $service->fetchStreamChunk($url, "bytes={$start}-{$end}") : null;
                    } catch (MusicStreamingException|InvalidArgumentException) {
                        break;
                    }
                }

                if ($response === null || $response->failed()) {
                    break;
                }

                $body = $response->body();
                $length = strlen($body);

                if ($length === 0) {
                    break;
                }

                echo $body;
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();

                $contentRange = (string) $response->header('Content-Range');
                if (preg_match('/bytes\s+\d+-(\d+)\/(\d+)/i', $contentRange, $matches)) {
                    $start = ((int) $matches[1]) + 1;
                    $total = (int) $matches[2];
                } else {
                    $start += $length;
                    if ($length < self::CHUNK_SIZE) {
                        break;
                    }
                }
            }
        }, $filename, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-store',
        ]);
    }
}
