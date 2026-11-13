<?php

namespace App\Livewire;

use App\Exceptions\MusicStreamingException;
use App\Livewire\Concerns\InteractsWithLibrary;
use App\Models\Favorite;
use App\Models\OfflineTrack;
use App\Models\Play;
use App\Services\ChartService;
use App\Services\MusicStreamingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Livewire\Attributes\Async;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Reproductor anclado. Vive dentro de @persist en el layout, así que ni el
 * componente ni su <audio> se destruyen al navegar con wire:navigate.
 *
 * Todo el estado de reproducción (cola, posición, volumen) está en el
 * navegador; el servidor solo resuelve URLs de stream, guarda favoritos y
 * anota lo que se ha escuchado.
 */
class Player extends Component
{
    use InteractsWithLibrary;

    /** Canciones del mismo artista que el modo DJ añade en cada tanda. */
    private const DJ_ARTIST_TRACKS = 6;

    /** Canciones populares del día con las que el modo DJ completa la tanda. */
    private const DJ_POPULAR_TRACKS = 4;

    /**
     * Lo llama el JavaScript del reproductor: `await $wire.resolveStream(id, fresh)`.
     *
     * Con $fresh = true se salta la caché; es la petición "silenciosa" que
     * hace el reproductor cuando la URL actual falla o ha caducado.
     *
     * @return array{ok: bool, message?: string, stream?: array<string, mixed>, is_favorite?: bool}
     */
    #[Async]
    #[Renderless]
    public function resolveStream(string $videoId, bool $fresh = false): array
    {
        try {
            $stream = app(MusicStreamingService::class)->getStream($videoId, $fresh);
        } catch (MusicStreamingException|InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $stream['sources'] = array_map(
            fn (array $source) => Arr::except($source, 'upstream_url'),
            $stream['sources'],
        );

        return [
            'ok' => true,
            'stream' => Arr::except($stream, 'instance'),
            'is_favorite' => Favorite::where('video_id', $videoId)->exists(),
        ];
    }

    /**
     * Lo llama el reproductor cuando una canción empieza a sonar de verdad:
     * así el inicio puede mostrar lo escuchado recientemente.
     *
     * @param  array<string, mixed>  $track
     */
    #[Async]
    #[Renderless]
    public function recordPlay(array $track): void
    {
        $duration = max(0, (int) ($track['duration'] ?? 210));
        Play::record($this->validatedTrack($track), $duration > 0 ? $duration : 210);
    }

    /**
     * Modo DJ: cuando la fila se acaba, el reproductor pide una tanda de
     * canciones que peguen con la que suena (más del mismo artista y algunas
     * de lo más escuchado hoy), sin repetir las que ya están en la fila.
     *
     * @param  array<string, mixed>  $track
     * @param  array<int, mixed>  $queuedIds  IDs de vídeo que ya están en la fila
     * @return list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}>
     */
    #[Async]
    #[Renderless]
    public function djMix(array $track, array $queuedIds = []): array
    {
        $track = $this->validatedTrack($track);
        $skip = [...array_filter($queuedIds, 'is_string'), ...$this->excludedIds, $track['video_id']];

        try {
            $sameArtist = $track['artist'] !== ''
                ? app(MusicStreamingService::class)->artistTracks($track['artist'])
                : [];
        } catch (MusicStreamingException) {
            $sameArtist = [];
        }

        $pick = fn (array $tracks, int $count) => collect($tracks)
            ->reject(fn (array $candidate) => in_array($candidate['video_id'], $skip, true))
            ->shuffle()
            ->take($count);

        return $pick($sameArtist, self::DJ_ARTIST_TRACKS)
            ->concat($pick(app(ChartService::class)->dailyPicks(), self::DJ_POPULAR_TRACKS))
            ->unique('video_id')
            ->shuffle()
            ->map(fn (array $candidate) => [
                ...Arr::only($candidate, ['video_id', 'title', 'artist', 'thumbnail_url']),
                'duration' => (int) ($candidate['duration'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Registra en la base de datos que la canción quedó guardada en el
     * almacenamiento offline interno (IndexedDB) del navegador.
     *
     * @param  array<string, mixed>  $track
     */
    #[Async]
    #[Renderless]
    public function markOfflineSaved(array $track, int $sizeBytes = 0, string $mimeType = 'audio/webm'): void
    {
        $valid = $this->validatedTrack($track);
        $duration = max(0, (int) ($track['duration'] ?? 0));

        OfflineTrack::updateOrCreate(
            ['video_id' => $valid['video_id']],
            [
                ...$valid,
                'duration' => $duration,
                'size_bytes' => max(0, $sizeBytes),
                'mime_type' => substr($mimeType ?: 'audio/webm', 0, 64),
            ],
        );
    }

    #[Async]
    #[Renderless]
    public function markOfflineRemoved(string $videoId): void
    {
        OfflineTrack::where('video_id', $videoId)->delete();
    }

    public function render(): View
    {
        return view('livewire.player');
    }
}
