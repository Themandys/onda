<?php

namespace Tests\Feature;

use App\Livewire\Genre;
use App\Livewire\NewReleases;
use App\Models\ExcludedTrack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'charts.test/mx/music/most-played/100/songs.json';

    private const ROCK_FEED = 'genres.test/mx/rss/topsongs/limit=100/genre=21/json';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'music.piped.instances' => ['https://piped.test'],
            'music.innertube.enabled' => false,
            'music.charts.endpoint' => 'https://charts.test',
            'music.charts.genre_endpoint' => 'https://genres.test',
            'music.charts.region' => 'mx',
            'music.charts.timezone' => 'America/Mexico_City',
            'music.charts.new_release_months' => 2,
            'music.charts.genres' => ['rock' => ['name' => 'Rock', 'tagline' => 'Guitarras', 'id' => 21, 'hue' => 8]],
        ]);
        Http::preventStrayRequests();

        $this->travelTo(Carbon::parse('2026-11-11 12:00', 'America/Mexico_City'));
    }

    public function test_genre_page_lists_its_most_popular_songs_without_the_excluded_ones(): void
    {
        ExcludedTrack::create(['video_id' => 'vid00000002', 'title' => 'Song 02', 'artist' => 'Artist', 'thumbnail_url' => 'https://i.ytimg.com/vi/vid00000002/hqdefault.jpg']);
        Http::fake([
            self::ROCK_FEED => Http::response($this->genreFeed([1 => '1991-09-10', 2 => '2001-01-01', 3 => '2026-10-20'])),
            'piped.test/search*' => $this->catalog(),
        ]);

        Livewire::test(Genre::class, ['genre' => 'rock'])
            ->assertSee('Rock')
            ->assertDontSee('Song 01')
            ->call('load')
            ->assertSeeInOrder(['Song 01', 'Song 03'])
            ->assertDontSee('Song 02');
    }

    public function test_genre_page_offers_a_retry_when_its_chart_is_unavailable(): void
    {
        Http::fake([self::ROCK_FEED => Http::response('', 503)]);

        Livewire::test(Genre::class, ['genre' => 'rock'])
            ->call('load')
            ->assertSee('No se pudo cargar la lista de Rock ahora mismo.')
            ->assertSee('Reintentar');
    }

    public function test_new_releases_page_lists_recent_songs_with_their_release_date(): void
    {
        Http::fake([
            self::FEED => Http::response(['feed' => ['results' => [
                ['id' => '1001', 'name' => 'Song 01', 'artistName' => 'Artist', 'releaseDate' => '2026-10-01'],
                ['id' => '1002', 'name' => 'Song 02', 'artistName' => 'Artist', 'releaseDate' => '2015-05-05'],
            ]]]),
            self::ROCK_FEED => Http::response($this->genreFeed([3 => '2026-11-10'])),
            'piped.test/search*' => $this->catalog(),
        ]);

        Livewire::test(NewReleases::class)
            ->call('load')
            ->assertSeeInOrder(['Song 03', 'Ayer', 'Song 01', '1 oct'])
            ->assertDontSee('Song 02');
    }

    /**
     * Lista de un género tal como la entrega iTunes: número de canción => fecha de publicación.
     *
     * @param  array<int, string>  $songs
     * @return array{feed: array{entry: list<array<string, mixed>>}}
     */
    private function genreFeed(array $songs): array
    {
        $entries = [];

        foreach ($songs as $number => $date) {
            $entries[] = [
                'id' => ['attributes' => ['im:id' => (string) (2000 + $number)]],
                'im:name' => ['label' => sprintf('Song %02d', $number)],
                'im:artist' => ['label' => 'Artist'],
                'im:releaseDate' => ['label' => $date.'T00:00:00-07:00'],
            ];
        }

        return ['feed' => ['entry' => $entries]];
    }

    /**
     * Catálogo de Piped simulado: "Song 07 Artist" → vídeo `vid00000007`.
     */
    private function catalog(): callable
    {
        return function (Request $request) {
            preg_match('/Song (\d+)/', (string) $request['q'], $match);

            return Http::response(['items' => [[
                'url' => '/watch?v='.sprintf('vid%08d', $match[1]),
                'type' => 'stream',
                'title' => 'Song '.$match[1],
                'uploaderName' => 'Artist',
                'duration' => 200,
            ]]]);
        };
    }
}
