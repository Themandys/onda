<?php

namespace Tests\Feature;

use App\Services\ChartService;
use ArrayObject;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class ChartServiceTest extends TestCase
{
    private const FEED = 'charts.test/mx/music/most-played/100/songs.json';

    private const ROCK_FEED = 'genres.test/mx/rss/topsongs/limit=100/genre=21/json';

    private const GENRES = ['rock' => ['name' => 'Rock', 'tagline' => 'Guitarras', 'id' => 21, 'hue' => 8]];

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

    public function test_entries_are_normalized_and_cached(): void
    {
        Http::fake([self::FEED => Http::response($this->feed(['2026-10-08', null]))]);

        $entries = app(ChartService::class)->entries();
        app(ChartService::class)->entries();

        $this->assertSame([
            ['id' => '1001', 'title' => 'Song 01', 'artist' => 'Artist', 'released_at' => '2026-10-08'],
            ['id' => '1002', 'title' => 'Song 02', 'artist' => 'Artist', 'released_at' => null],
        ], $entries);
        Http::assertSentCount(1);
    }

    public function test_feed_is_requested_over_http2_without_a_json_accept_header(): void
    {
        $versions = new ArrayObject;
        Http::globalMiddleware(fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler, $versions) {
            $versions[] = $request->getProtocolVersion();

            return $handler($request, $options);
        });
        Http::fake([self::FEED => Http::response($this->feed(['2026-10-08']))]);

        app(ChartService::class)->entries();

        $this->assertSame(['2.0'], $versions->getArrayCopy());
        Http::assertSent(fn (Request $request) => ! $request->hasHeader('Accept', 'application/json'));
    }

    public function test_failed_feed_returns_nothing_and_is_asked_again(): void
    {
        Http::fake([self::FEED => Http::sequence()
            ->push('', 503)
            ->push($this->feed(['2026-10-08']))]);

        $this->assertSame([], app(ChartService::class)->entries());
        $this->assertCount(1, app(ChartService::class)->entries());
    }

    public function test_new_releases_keep_songs_from_the_last_two_months_newest_first(): void
    {
        Http::fake([
            self::FEED => Http::response($this->feed([
                '2026-09-11', // justo en el límite: entra
                '2026-09-10', // un día antes del límite: fuera
                '2026-11-05',
                '2019-03-01',
                '2026-12-01', // todavía no ha salido: fuera
                null,
            ])),
            'piped.test/search*' => $this->catalog(),
        ]);

        $tracks = app(ChartService::class)->newReleases();

        $this->assertSame(['vid00000003', 'vid00000001'], array_column($tracks, 'video_id'));
        $this->assertSame(['2026-11-05', '2026-09-11'], array_column($tracks, 'released_at'));
        $this->assertSame('Song 03', $tracks[0]['title']);
    }

    public function test_daily_picks_stay_fixed_during_the_day_and_change_the_next_day(): void
    {
        Http::fake([
            self::FEED => Http::response($this->feed(array_fill(0, 20, '2026-01-01'))),
            'piped.test/search*' => $this->catalog(),
        ]);

        $today = array_column(app(ChartService::class)->dailyPicks(5), 'video_id');
        $this->travel(3)->hours();
        $later = array_column(app(ChartService::class)->dailyPicks(5), 'video_id');
        $this->travel(1)->days();
        $tomorrow = array_column(app(ChartService::class)->dailyPicks(5), 'video_id');

        $this->assertCount(5, $today);
        $this->assertSame($today, $later);
        $this->assertNotSame($today, $tomorrow);
    }

    public function test_picks_leave_out_the_given_songs_and_refill_from_the_spare_ones(): void
    {
        Http::fake([
            self::FEED => Http::response($this->feed(array_fill(0, 20, '2026-01-01'))),
            'piped.test/search*' => $this->catalog(),
        ]);

        $picks = array_column(app(ChartService::class)->dailyPicks(5), 'video_id');
        $filtered = array_column(app(ChartService::class)->dailyPicks(5, except: [$picks[0], $picks[1]]), 'video_id');

        $this->assertCount(5, $filtered);
        $this->assertNotContains($picks[0], $filtered);
        $this->assertNotContains($picks[1], $filtered);
    }

    public function test_each_song_is_searched_only_once_across_lists(): void
    {
        Http::fake([
            self::FEED => Http::response($this->feed(['2026-11-01', '2026-10-20', '2026-10-01'])),
            'piped.test/search*' => $this->catalog(),
        ]);

        app(ChartService::class)->dailyPicks(3);
        app(ChartService::class)->newReleases(3);

        $this->assertCount(3, Http::recorded(fn (Request $request) => str_contains($request->url(), 'piped.test/search')));
    }

    public function test_lists_are_resolved_again_after_the_catalog_was_unreachable(): void
    {
        Http::fake([
            self::FEED => Http::response($this->feed(['2026-11-01', '2026-10-20'])),
            'piped.test/search*' => Http::sequence()
                ->push('', 502)
                ->push('', 502)
                ->whenEmpty($this->catalogResponse('Song 01 Artist')),
        ]);

        $this->assertSame([], app(ChartService::class)->newReleases());
        $this->assertNotSame([], app(ChartService::class)->newReleases());
    }

    public function test_genre_tracks_follow_the_order_of_the_genre_chart(): void
    {
        config(['music.charts.genres' => self::GENRES]);
        Http::fake([
            self::ROCK_FEED => Http::response($this->genreFeed([7 => '1991-09-10', 3 => '2026-10-20'])),
            'piped.test/search*' => $this->catalog(),
        ]);

        $tracks = app(ChartService::class)->genreTracks('rock');

        $this->assertSame(['vid00000007', 'vid00000003'], array_column($tracks, 'video_id'));
        $this->assertSame(['1991-09-10', '2026-10-20'], array_column($tracks, 'released_at'));
    }

    public function test_genre_tracks_are_empty_for_an_unknown_genre(): void
    {
        config(['music.charts.genres' => self::GENRES]);

        $this->assertSame([], app(ChartService::class)->genreTracks('polka'));
        Http::assertNothingSent();
    }

    public function test_a_genre_chart_with_a_single_song_is_still_read(): void
    {
        config(['music.charts.genres' => self::GENRES]);
        Http::fake([
            self::ROCK_FEED => Http::response(['feed' => ['entry' => $this->genreFeed([7 => '1991-09-10'])['feed']['entry'][0]]]),
            'piped.test/search*' => $this->catalog(),
        ]);

        $this->assertSame(['vid00000007'], array_column(app(ChartService::class)->genreTracks('rock'), 'video_id'));
    }

    public function test_new_releases_add_recent_songs_from_the_genre_charts_without_repeating_them(): void
    {
        config(['music.charts.genres' => self::GENRES]);
        Http::fake([
            self::FEED => Http::response($this->feed(['2026-10-08', '2019-03-01'])),
            self::ROCK_FEED => Http::response($this->genreFeed([
                1 => '2026-10-08', // ya está en la lista general
                7 => '2026-11-02',
                8 => '1991-09-10',
            ])),
            'piped.test/search*' => $this->catalog(),
        ]);

        $tracks = app(ChartService::class)->newReleases();

        $this->assertSame(['vid00000007', 'vid00000001'], array_column($tracks, 'video_id'));
    }

    public function test_new_releases_survive_a_genre_chart_that_does_not_answer(): void
    {
        config(['music.charts.genres' => self::GENRES]);
        Http::fake([
            self::FEED => Http::response($this->feed(['2026-10-08'])),
            self::ROCK_FEED => Http::response('', 503),
            'piped.test/search*' => $this->catalog(),
        ]);

        $this->assertSame(['vid00000001'], array_column(app(ChartService::class)->newReleases(), 'video_id'));
    }

    #[TestWith(['2026-11-11', 'Hoy'])]
    #[TestWith(['2026-11-10', 'Ayer'])]
    #[TestWith(['2026-11-05', 'Hace 6 días'])]
    #[TestWith(['2026-11-04', '4 nov'])]
    #[TestWith([null, null])]
    public function test_release_dates_are_shown_in_short_form(?string $date, ?string $label): void
    {
        $this->assertSame($label, app(ChartService::class)->releaseLabel($date));
    }

    public function test_region_name_is_shown_in_spanish(): void
    {
        $this->assertSame('México', app(ChartService::class)->regionName());
    }

    /**
     * Feed de la lista de éxitos con una canción por fecha de publicación.
     *
     * @param  list<string|null>  $releaseDates
     * @return array{feed: array{results: list<array<string, string>>}}
     */
    private function feed(array $releaseDates): array
    {
        $results = [];

        foreach ($releaseDates as $index => $date) {
            $results[] = array_filter([
                'id' => (string) (1001 + $index),
                'name' => sprintf('Song %02d', $index + 1),
                'artistName' => 'Artist',
                'releaseDate' => $date,
            ]);
        }

        return ['feed' => ['results' => $results]];
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
                'id' => ['attributes' => ['im:id' => (string) (1000 + $number)]],
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
        return fn (Request $request) => $this->catalogResponse((string) $request['q']);
    }

    private function catalogResponse(string $query): mixed
    {
        preg_match('/Song (\d+)/', $query, $match);

        return Http::response(['items' => [[
            'url' => '/watch?v='.sprintf('vid%08d', $match[1]),
            'type' => 'stream',
            'title' => 'Song '.$match[1],
            'uploaderName' => 'Artist',
            'duration' => 200,
        ]]]);
    }
}
