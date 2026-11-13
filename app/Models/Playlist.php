<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Playlist extends Model
{
    /**
     * @return HasMany<PlaylistTrack, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(PlaylistTrack::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Añade la canción al final de la lista. Devuelve false si ya estaba.
     *
     * @param  array{video_id: string, title: string, artist: string, thumbnail_url: string}  $track
     */
    public function addTrack(array $track): bool
    {
        if ($this->tracks()->where('video_id', $track['video_id'])->exists()) {
            return false;
        }

        $this->tracks()->create([
            ...$track,
            'position' => (int) $this->tracks()->max('position') + 1,
        ]);
        $this->touch();

        return true;
    }
}
