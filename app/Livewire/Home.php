<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithLibrary;
use App\Models\Play;
use App\Services\ChartService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Inicio')]
class Home extends Component
{
    use InteractsWithLibrary;

    private const RECENT_LIMIT = 12;

    private const SUGGESTION_LIMIT = 12;

    /** Novedades que caben en la portada; el resto está en su propia página. */
    private const NEW_RELEASE_LIMIT = 10;

    /**
     * Las sugerencias y las novedades dependen de servicios externos: se
     * piden después del primer render (wire:init) para no retrasar la página.
     */
    public bool $discoveryLoaded = false;

    public function loadDiscovery(): void
    {
        $this->discoveryLoaded = true;
    }

    /**
     * Favoritos o exclusiones cambiaron desde el reproductor u otra lista.
     */
    #[On('library-changed')]
    public function refreshLibrary(): void {}

    /**
     * El reproductor avisa cuando empieza a sonar una canción nueva.
     */
    #[On('play-recorded')]
    public function refreshRecent(): void {}

    public function render(): View
    {
        $charts = app(ChartService::class);
        $newReleases = $this->discoveryLoaded ? $charts->newReleases(self::NEW_RELEASE_LIMIT, $this->excludedIds) : [];

        return view('livewire.home', [
            'name' => Str::before((string) auth()->user()?->name, ' '),
            'recent' => Play::query()
                ->whereNotIn('video_id', $this->excludedIds)
                ->latest('last_played_at')
                ->limit(self::RECENT_LIMIT)
                ->get()
                ->map(fn (Play $play) => $play->toTrack())
                ->all(),
            'genres' => $charts->genres(),
            'suggestions' => $this->discoveryLoaded ? $charts->dailyPicks(self::SUGGESTION_LIMIT, $this->excludedIds) : [],
            'newReleases' => array_map(
                fn (array $track) => [...$track, 'released_label' => $charts->releaseLabel($track['released_at'])],
                $newReleases,
            ),
            'region' => $charts->regionName(),
            'months' => (int) config('music.charts.new_release_months', 2),
        ]);
    }
}
