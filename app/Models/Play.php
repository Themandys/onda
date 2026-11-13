<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['video_id', 'title', 'artist', 'thumbnail_url', 'play_count', 'last_played_at'])]
class Play extends Model
{
    /**
     * Anota que la canción ha sonado: la sube al principio del historial,
     * suma una reproducción y actualiza el resumen mensual.
     *
     * @param  array{video_id: string, title: string, artist: string, thumbnail_url: string}  $track
     */
    public static function record(array $track, int $duration = 210): self
    {
        $play = static::firstOrNew(['video_id' => $track['video_id']]);

        $play->fill([
            ...$track,
            'play_count' => $play->play_count + 1,
            'last_played_at' => now(),
        ])->save();

        MonthlyPlay::record($track, $duration);

        return $play;
    }

    /**
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string}
     */
    public function toTrack(): array
    {
        return $this->only(['video_id', 'title', 'artist', 'thumbnail_url']);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'play_count' => 'integer',
            'last_played_at' => 'datetime',
        ];
    }
}
