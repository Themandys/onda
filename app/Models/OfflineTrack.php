<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['video_id', 'title', 'artist', 'thumbnail_url', 'duration', 'size_bytes', 'mime_type'])]
class OfflineTrack extends Model
{
    use BelongsToUser;

    /**
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}
     */
    public function toTrack(): array
    {
        return [
            'video_id' => $this->video_id,
            'title' => $this->title,
            'artist' => $this->artist,
            'thumbnail_url' => $this->thumbnail_url,
            'duration' => (int) $this->duration,
        ];
    }

    /**
     * Tamaño del audio guardado, en megas ("3.3 MB").
     */
    public function formattedSize(): string
    {
        return number_format($this->size_bytes / 1_048_576, 1).' MB';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'size_bytes' => 'integer',
        ];
    }
}
