<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithLibrary;
use App\Models\ExcludedTrack;
use App\Models\Favorite;
use App\Models\OfflineTrack;
use App\Models\Playlist;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tu biblioteca')]
class Library extends Component
{
    use InteractsWithLibrary;

    /** Portadas que forman el mosaico de cada colección. */
    private const MOSAIC_COVERS = 4;

    public string $newPlaylist = '';

    #[On('library-changed')]
    #[On('offline-changed')]
    public function refreshLibrary(): void {}

    public function removeOfflineTrack(string $videoId): void
    {
        OfflineTrack::where('video_id', $videoId)->delete();

        $this->dispatch('offline-remove-blob', videoId: $videoId);
        $this->dispatch('toast', message: 'Canción quitada del almacenamiento sin internet');
    }

    public function createPlaylist(): void
    {
        $this->newPlaylist = trim($this->newPlaylist);

        $this->validate(
            ['newPlaylist' => ['required', 'string', 'max:60']],
            ['newPlaylist.required' => 'Ponle un nombre a la playlist.', 'newPlaylist.max' => 'Máximo 60 caracteres.'],
        );

        $playlist = Playlist::create(['name' => $this->newPlaylist]);

        $this->reset('newPlaylist');
        unset($this->playlists);

        $this->dispatch('library-changed');
        $this->dispatch('toast', message: "Playlist «{$playlist->name}» creada");
    }

    /**
     * Devuelve una canción excluida a las listas y búsquedas.
     */
    public function restoreTrack(string $videoId): void
    {
        ExcludedTrack::where('video_id', $videoId)->delete();
        unset($this->excludedIds);

        $this->dispatch('toast', message: 'Canción restaurada');
    }

    public function render(): View
    {
        return view('livewire.library', [
            'collections' => Playlist::query()
                ->withCount('tracks')
                ->with(['tracks' => fn ($tracks) => $tracks->limit(self::MOSAIC_COVERS)])
                ->latest('updated_at')
                ->latest('id')
                ->get(),
            'favoriteCovers' => Favorite::latest('id')->limit(self::MOSAIC_COVERS)->pluck('thumbnail_url')->all(),
            'offlineTracks' => OfflineTrack::latest('updated_at')->get(),
            'excluded' => ExcludedTrack::latest('id')->get(),
        ]);
    }
}
