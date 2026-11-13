<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['video_id', 'title', 'artist', 'thumbnail_url', 'position'])]
class PlaylistTrack extends Model
{
    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string}
     */
    public function toTrack(): array
    {
        return $this->only(['video_id', 'title', 'artist', 'thumbnail_url']);
    }
}
