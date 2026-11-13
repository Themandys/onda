<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithLibrary;
use App\Models\Playlist;
use App\Models\PlaylistTrack;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ShowPlaylist extends Component
{
    use InteractsWithLibrary;

    public Playlist $playlist;

    public string $name = '';

    public function mount(Playlist $playlist): void
    {
        $this->playlist = $playlist;
        $this->name = $playlist->name;
    }

    #[On('library-changed')]
    public function refreshLibrary(): void {}

    public function rename(): void
    {
        $this->name = trim($this->name);

        $this->validate(
            ['name' => ['required', 'string', 'max:60']],
            ['name.required' => 'La playlist necesita un nombre.', 'name.max' => 'Máximo 60 caracteres.'],
        );

        if ($this->name === $this->playlist->name) {
            return;
        }

        $this->playlist->update(['name' => $this->name]);
        unset($this->playlists);

        $this->dispatch('library-changed');
        $this->dispatch('toast', message: "Ahora se llama «{$this->name}»");
    }

    public function removeFromPlaylist(int $trackId): void
    {
        $this->playlist->tracks()->whereKey($trackId)->delete();
        $this->playlist->touch();

        unset($this->playlists);
        $this->dispatch('library-changed');
    }

    public function deletePlaylist(): void
    {
        $this->playlist->delete();

        $this->dispatch('toast', message: "Playlist «{$this->playlist->name}» eliminada");
        $this->redirectRoute('library', navigate: true);
    }

    public function render(): View
    {
        $tracks = $this->playlist->tracks()->get();

        return view('livewire.show-playlist', [
            'tracks' => $tracks->map(fn (PlaylistTrack $track) => ['id' => $track->id, ...$track->toTrack()])->all(),
            'covers' => $tracks->pluck('thumbnail_url')->all(),
        ])->title($this->playlist->name);
    }
}
