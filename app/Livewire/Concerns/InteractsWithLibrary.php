<?php

namespace App\Livewire\Concerns;

use App\Models\ExcludedTrack;
use App\Models\Favorite;
use App\Models\Playlist;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;

/**
 * Acciones de biblioteca compartidas por las páginas y el reproductor.
 * Las canciones llegan desde el navegador, así que siempre se validan.
 */
trait InteractsWithLibrary
{
    /**
     * @param  array<string, mixed>  $track
     */
    public function toggleFavorite(array $track): bool
    {
        $track = $this->validatedTrack($track);
        $favorite = Favorite::toggle($track);

        // Guardarla como favorita deshace una exclusión anterior.
        if ($favorite && ExcludedTrack::where('video_id', $track['video_id'])->delete()) {
            unset($this->excludedIds);
        }

        unset($this->favoriteIds);

        // El reproductor escucha este evento para sincronizar su corazón.
        $this->dispatch('favorite-changed', videoId: $track['video_id'], favorite: $favorite);
        $this->dispatch('toast', message: $favorite
            ? "«{$track['title']}» añadida a Favoritos"
            : "«{$track['title']}» quitada de Favoritos");

        return $favorite;
    }

    /**
     * @param  array<string, mixed>  $track
     */
    public function addToPlaylist(int $playlistId, array $track): void
    {
        $playlist = Playlist::findOrFail($playlistId);
        $added = $playlist->addTrack($this->validatedTrack($track));

        unset($this->playlists);

        $this->dispatch('toast', message: $added
            ? "Añadida a «{$playlist->name}»"
            : "Ya estaba en «{$playlist->name}»");
    }

    /**
     * Excluye la canción: deja de aparecer en el inicio, las sugerencias y
     * las búsquedas, y sale de favoritos y de la fila de reproducción.
     *
     * @param  array<string, mixed>  $track
     */
    public function excludeTrack(array $track): void
    {
        $track = $this->validatedTrack($track);

        ExcludedTrack::updateOrCreate(['video_id' => $track['video_id']], $track);

        if (Favorite::where('video_id', $track['video_id'])->delete()) {
            $this->dispatch('favorite-changed', videoId: $track['video_id'], favorite: false);
        }

        unset($this->excludedIds, $this->favoriteIds);

        // El reproductor la quita de la fila (y salta si era la que sonaba).
        $this->dispatch('track-excluded', videoId: $track['video_id']);
        $this->dispatch('toast', message: "«{$track['title']}» excluida. Puedes restaurarla en tu biblioteca.");
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function favoriteIds(): array
    {
        return Favorite::pluck('video_id')->all();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function excludedIds(): array
    {
        return ExcludedTrack::pluck('video_id')->all();
    }

    /**
     * Quita de una lista las canciones excluidas.
     *
     * @template TTrack of array<string, mixed>
     *
     * @param  list<TTrack>  $tracks
     * @return list<TTrack>
     */
    protected function withoutExcluded(array $tracks): array
    {
        if ($this->excludedIds === []) {
            return $tracks;
        }

        return array_values(array_filter(
            $tracks,
            fn (array $track) => ! in_array($track['video_id'], $this->excludedIds, true),
        ));
    }

    /**
     * @return Collection<int, Playlist>
     */
    #[Computed]
    public function playlists(): Collection
    {
        return Playlist::withCount('tracks')->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $track
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string}
     */
    protected function validatedTrack(array $track): array
    {
        if (isset($track['thumbnail_url']) && is_string($track['thumbnail_url'])) {
            $thumb = trim($track['thumbnail_url']);
            $track['thumbnail_url'] = $thumb === ''
                ? null
                : preg_replace('/^http:\/\//i', 'https://', $thumb);
        }

        $data = Validator::make($track, [
            'video_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{11}$/'],
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048', 'starts_with:https://'],
        ])->validate();

        return [
            'video_id' => $data['video_id'],
            'title' => $data['title'],
            'artist' => $data['artist'] ?? '',
            'thumbnail_url' => $data['thumbnail_url'] ?? "https://i.ytimg.com/vi/{$data['video_id']}/hqdefault.jpg",
        ];
    }
}
