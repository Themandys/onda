<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['video_id', 'title', 'artist', 'thumbnail_url'])]
class ExcludedTrack extends Model
{
    /**
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string}
     */
    public function toTrack(): array
    {
        return $this->only(['video_id', 'title', 'artist', 'thumbnail_url']);
    }
}
