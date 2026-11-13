<?php

namespace App\Livewire;

use App\Models\Favorite;
use App\Models\Playlist;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Accesos de la barra lateral a favoritos y playlists. Es un componente
 * aparte para que se actualice solo cuando la biblioteca cambia.
 */
class NavLibrary extends Component
{
    /** Playlists que caben en la barra lateral; el resto está en la biblioteca. */
    private const PLAYLIST_LIMIT = 12;

    /**
     * Enlace que corresponde a la página abierta: 'favorites', 'playlist:{id}' o ''.
     * Se fija al montar porque al refrescarse ya no se conoce la ruta de la página.
     */
    public string $current = '';

    public function mount(): void
    {
        $playlist = request()->route('playlist');

        $this->current = match (true) {
            request()->routeIs('favorites') => 'favorites',
            request()->routeIs('playlists.show') => 'playlist:'.($playlist instanceof Playlist ? $playlist->id : (int) $playlist),
            default => '',
        };
    }

    #[On('library-changed')]
    #[On('favorite-changed')]
    public function refreshLibrary(): void {}

    public function render(): View
    {
        return view('livewire.nav-library', [
            'favorites' => Favorite::count(),
            'playlists' => Playlist::query()
                ->withCount('tracks')
                ->latest('updated_at')
                ->latest('id')
                ->limit(self::PLAYLIST_LIMIT)
                ->get(),
        ]);
    }
}
