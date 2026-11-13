<?php

namespace Tests\Feature;

use App\Livewire\Home;
use App\Models\Favorite;
use App\Models\Play;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'charts.test/mx/music/most-played/100/songs.json';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'music.piped.instances' => ['https://piped.test'],
            'music.innertube.enabled' => false,
            'music.charts.endpoint' => 'https://charts.test',
            'music.charts.region' => 'mx',
            'music.charts.timezone' => 'America/Mexico_City',
            'music.charts.new_release_months' => 2,
            'music.charts.genre_endpoint' => 'https://genres.test',
            'music.charts.genres' => [],
        ]);
        Http::preventStrayRequests();

        $this->travelTo(Carbon::parse('2026-11-11 12:00', 'America/Mexico_City'));
    }

    public function test_recently_played_songs_are_listed_most_recent_first(): void
    {
        Play::record($this->track(1));
        $this->travel(5)->minutes();
        Play::record($this->track(2));

        Livewire::test(Home::class)
            ->assertSeeInOrder(['Escuchadas recientemente', 'Song 02', 'Song 01'])
            ->assertSee('Cargando sugerencias');
    }

    public function test_discovery_shows_daily_suggestions_and_new_releases(): void
    {
        $this->fakeCharts(['2026-11-09', '2026-10-01', '2020-05-05']);

        Livewire::test(Home::class)
            ->call('loadDiscovery')
            ->assertDontSee('Cargando sugerencias')
            ->assertSee('México')
            ->assertViewHas('suggestions', fn (array $tracks) => collect($tracks)->pluck('video_id')->sort()->values()->all()
                === ['vid00000001', 'vid00000002', 'vid00000003'])
            ->assertViewHas('newReleases', fn (array $tracks) => array_column($tracks, 'video_id') === ['vid00000001', 'vid00000002']
                && array_column($tracks, 'released_label') === ['Hace 2 días', '1 oct']);
    }

    public function test_excluding_a_song_removes_it_from_every_list_and_from_favorites(): void
    {
        $this->fakeCharts(['2026-11-09', '2026-10-01']);
        Play::record($this->track(1));
        Favorite::create($this->track(1));

        Livewire::test(Home::class)
            ->call('loadDiscovery')
            ->assertSee('Song 01')
            ->call('excludeTrack', $this->track(1))
            ->assertDispatched('track-excluded', videoId: 'vid00000001')
            ->assertDispatched('favorite-changed', videoId: 'vid00000001', favorite: false)
            ->assertDontSee('Song 01')
            ->assertSee('Song 02');

        $this->assertDatabaseHas('excluded_tracks', ['video_id' => 'vid00000001']);
        $this->assertDatabaseMissing('favorites', ['video_id' => 'vid00000001']);
    }

    public function test_home_links_to_every_genre(): void
    {
        config(['music.charts.genres' => [
            'rock' => ['name' => 'Rock', 'tagline' => 'Guitarras', 'id' => 21, 'hue' => 8],
            'reggaeton' => ['name' => 'Reguetón', 'tagline' => 'Urbano latino', 'id' => 1119, 'hue' => 268],
        ]]);

        Livewire::test(Home::class)
            ->assertSeeInOrder(['Explora por género', 'Rock', 'Reguetón'])
            ->assertSeeHtml('href="'.route('genres.show', 'rock').'"')
            ->assertSeeHtml('href="'.route('genres.show', 'reggaeton').'"');
    }

    public function test_home_offers_a_retry_when_suggestions_are_unavailable(): void
    {
        Http::fake([self::FEED => Http::response('', 503)]);

        Livewire::test(Home::class)
            ->call('loadDiscovery')
            ->assertSee('No se pudieron cargar las sugerencias ahora mismo.')
            ->assertSee('Reintentar');
    }

    /**
     * Simula la lista de éxitos (una canción por fecha) y el catálogo de Piped
     * que convierte "Song 07 Artist" en el vídeo `vid00000007`.
     *
     * @param  list<string>  $releaseDates
     */
    private function fakeCharts(array $releaseDates): void
    {
        $results = [];

        foreach ($releaseDates as $index => $date) {
            $results[] = [
                'id' => (string) (1001 + $index),
                'name' => sprintf('Song %02d', $index + 1),
                'artistName' => 'Artist',
                'releaseDate' => $date,
            ];
        }

        Http::fake([
            self::FEED => Http::response(['feed' => ['results' => $results]]),
            'piped.test/search*' => function (Request $request) {
                preg_match('/Song (\d+)/', (string) $request['q'], $match);

                return Http::response(['items' => [[
                    'url' => '/watch?v='.sprintf('vid%08d', $match[1]),
                    'type' => 'stream',
                    'title' => 'Song '.$match[1],
                    'uploaderName' => 'Artist',
                    'duration' => 200,
                ]]]);
            },
        ]);
    }

    /**
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string}
     */
    private function track(int $number): array
    {
        return [
            'video_id' => sprintf('vid%08d', $number),
            'title' => sprintf('Song %02d', $number),
            'artist' => 'Artist',
            'thumbnail_url' => 'https://yt3.googleusercontent.com/cover=w544-h544-l90-rw',
        ];
    }
}
