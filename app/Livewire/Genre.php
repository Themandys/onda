<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithLibrary;
use App\Services\ChartService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Las canciones más populares de un género (rock, pop, reguetón…).
 */
class Genre extends Component
{
    use InteractsWithLibrary;

    private const TRACK_LIMIT = 30;

    public string $genre = '';

    /**
     * La lista depende de servicios externos: se pide después del primer
     * render (wire:init) para no retrasar la página.
     */
    public bool $loaded = false;

    public function mount(string $genre): void
    {
        abort_if(app(ChartService::class)->genre($genre) === null, 404);

        $this->genre = $genre;
    }

    public function load(): void
    {
        $this->loaded = true;
    }

    #[On('library-changed')]
    public function refreshLibrary(): void {}

    public function render(): View
    {
        $charts = app(ChartService::class);
        $details = $charts->genre($this->genre);

        return view('livewire.genre', [
            'details' => $details,
            'genres' => $charts->genres(),
            'region' => $charts->regionName(),
            'tracks' => $this->loaded ? $charts->genreTracks($this->genre, self::TRACK_LIMIT, $this->excludedIds) : [],
        ])->title($details['name']);
    }
}
