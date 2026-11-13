<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['year_month', 'video_id', 'title', 'artist', 'thumbnail_url', 'duration', 'play_count', 'last_played_at'])]
class MonthlyPlay extends Model
{
    /**
     * Registra una reproducción en el mes actual (formato `YYYY-MM`).
     *
     * @param  array{video_id: string, title: string, artist: string, thumbnail_url: string}  $track
     */
    public static function record(array $track, int $duration = 210): self
    {
        $now = now();
        $yearMonth = $now->format('Y-m');

        $entry = static::firstOrNew([
            'year_month' => $yearMonth,
            'video_id' => $track['video_id'],
        ]);

        $entry->fill([
            ...$track,
            'duration' => $duration > 0 ? $duration : ($entry->duration ?: 210),
            'play_count' => $entry->play_count + 1,
            'last_played_at' => $now,
        ])->save();

        return $entry;
    }

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'play_count' => 'integer',
            'last_played_at' => 'datetime',
        ];
    }
}
