<?php

namespace App\Livewire;

use App\Exceptions\MusicStreamingException;
use App\Livewire\Concerns\InteractsWithLibrary;
use App\Services\ChartService;
use App\Services\MusicStreamingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Buscar')]
class Search extends Component
{
    use InteractsWithLibrary;

    /** Artistas que se destacan sobre los resultados. */
    private const ARTIST_LIMIT = 8;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    public function mount(): void
    {
        // Los enlaces antiguos al catálogo de un artista (`?artist=`) van ahora a su perfil.
        $artist = Str::squish((string) request()->query('artist', ''));

        if ($artist !== '') {
            $this->redirectRoute('artist', ['name' => $artist], navigate: true);
        }
    }

    /**
     * El reproductor avisa cuando cambia un favorito desde su corazón;
     * basta con volver a renderizar para refrescar los de la lista.
     */
    #[On('library-changed')]
    public function refreshLibrary(): void {}

    public function render(): View
    {
        $results = [];
        $error = null;

        if (Str::length(trim($this->query)) >= 2) {
            try {
                $results = $this->withoutExcluded(app(MusicStreamingService::class)->search($this->query));
            } catch (MusicStreamingException) {
                $error = 'No se pudo completar la búsqueda. Inténtalo de nuevo en unos segundos.';
            }
        }

        return view('livewire.search', [
            'results' => $results,
            'artists' => $this->extractArtists($results, $this->query),
            'genres' => app(ChartService::class)->genres(),
            'error' => $error,
        ]);
    }

    /**
     * Agrupa los artistas presentes en los resultados de búsqueda para mostrar
     * accesos directos a su perfil.
     *
     * @param  list<array{video_id: string, title: string, artist: string, thumbnail_url: string, duration: int}>  $results
     * @return list<array{name: string, thumbnail_url: string, count: int}>
     */
    private function extractArtists(array $results, string $query): array
    {
        $needle = Str::lower(Str::squish($query));

        return collect($results)
            ->filter(fn (array $track) => filled($track['artist'] ?? null))
            ->groupBy(fn (array $track) => Str::lower($track['artist']))
            ->map(function ($group): array {
                $first = $group->first();

                return [
                    'name' => $first['artist'],
                    'thumbnail_url' => $first['thumbnail_url'],
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc(function (array $artist) use ($needle): int {
                $matchesQuery = Str::contains(Str::lower($artist['name']), $needle) ? 1000 : 0;

                return $matchesQuery + $artist['count'];
            })
            ->take(self::ARTIST_LIMIT)
            ->values()
            ->all();
    }
}
