<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['video_id', 'title', 'artist', 'thumbnail_url'])]
class Favorite extends Model
{
    /**
     * Añade o quita la canción de favoritos. Devuelve true si queda guardada.
     *
     * @param  array{video_id: string, title: string, artist: string, thumbnail_url: string}  $track
     */
    public static function toggle(array $track): bool
    {
        if (static::where('video_id', $track['video_id'])->delete()) {
            return false;
        }

        static::create($track);

        return true;
    }

    /**
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string}
     */
    public function toTrack(): array
    {
        return $this->only(['video_id', 'title', 'artist', 'thumbnail_url']);
    }
}
