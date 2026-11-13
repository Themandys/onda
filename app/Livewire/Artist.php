<?php

namespace App\Livewire;

use App\Exceptions\MusicStreamingException;
use App\Livewire\Concerns\InteractsWithLibrary;
use App\Services\ArtistInfoService;
use App\Services\MusicStreamingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Perfil de un artista: su foto de fondo, quién es y sus canciones.
 */
class Artist extends Component
{
    use InteractsWithLibrary;

    /** Canciones que se ven antes de pulsar "Ver todas". */
    private const VISIBLE_TRACKS = 20;

    #[Url(as: 'name', except: '')]
    public string $name = '';

    /**
     * La ficha y el catálogo dependen de servicios externos: se piden
     * después del primer render (wire:init) para no retrasar la página.
     */
    public bool $loaded = false;

    public bool $showAll = false;

    public function mount(): void
    {
        $this->name = Str::limit(Str::squish($this->name), 120, '');

        abort_if($this->name === '', 404);
    }

    public function load(): void
    {
        $this->loaded = true;
    }

    public function showAllTracks(): void
    {
        $this->showAll = true;
    }

    #[On('library-changed')]
    public function refreshLibrary(): void {}

    public function render(): View
    {
        $info = null;
        $tracks = [];
        $error = null;

        if ($this->loaded) {
            $info = app(ArtistInfoService::class)->find($this->name);

            try {
                $tracks = $this->withoutExcluded(app(MusicStreamingService::class)->artistTracks($this->name));
            } catch (MusicStreamingException) {
                $error = 'No se pudieron cargar las canciones ahora mismo. Inténtalo de nuevo en unos segundos.';
            }
        }

        return view('livewire.artist', [
            'info' => $info,
            'tracks' => $this->showAll ? $tracks : array_slice($tracks, 0, self::VISIBLE_TRACKS),
            'total' => count($tracks),
            'error' => $error,
        ])->title($this->name);
    }
}
