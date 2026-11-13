<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithLibrary;
use App\Services\ChartService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Todas las novedades: canciones publicadas en los últimos meses.
 */
#[Title('Novedades')]
class NewReleases extends Component
{
    use InteractsWithLibrary;

    private const TRACK_LIMIT = 40;

    /**
     * La lista depende de servicios externos: se pide después del primer
     * render (wire:init) para no retrasar la página.
     */
    public bool $loaded = false;

    public function load(): void
    {
        $this->loaded = true;
    }

    #[On('library-changed')]
    public function refreshLibrary(): void {}

    public function render(): View
    {
        $charts = app(ChartService::class);
        $tracks = $this->loaded ? $charts->newReleases(self::TRACK_LIMIT, $this->excludedIds) : [];

        return view('livewire.new-releases', [
            'tracks' => array_map(
                fn (array $track) => [...$track, 'released_label' => $charts->releaseLabel($track['released_at'])],
                $tracks,
            ),
            'months' => (int) config('music.charts.new_release_months', 2),
        ]);
    }
}
