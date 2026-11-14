<?php

namespace Tests\Feature;

use App\Livewire\Artist;
use App\Models\ExcludedTrack;
use App\Models\User;
use App\Services\ArtistInfoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ArtistTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO = 'https://cdn.deezer.test/images/artist/abc123/1000x1000.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        config([
            'music.piped.instances' => ['https://piped.test'],
            'music.innertube.enabled' => false,
            'music.artists.deezer_endpoint' => 'https://deezer.test',
            'music.artists.wikipedia_endpoint' => 'https://wiki.test',
        ]);
        Http::preventStrayRequests();
    }

    public function test_artist_page_shows_the_photo_the_biography_and_the_songs_once_loaded(): void
    {
        Http::fake([
            'deezer.test/search/artist*' => Http::response(['data' => [$this->deezerArtist(27, 'Daft Punk', 4_500_000)]]),
            'deezer.test/artist/27/related*' => Http::response(['data' => [$this->deezerArtist(28, 'Justice', 900_000)]]),
            'wiki.test/w/api.php*' => Http::response($this->wikipedia([
                ['title' => 'Daft Punk', 'description' => 'dúo francés de música electrónica', 'extract' => 'Daft Punk fue un dúo de música electrónica formado en París.'],
            ])),
            'piped.test/search*' => Http::response(['items' => [
                ['url' => '/watch?v=4D7u5KF7SP8', 'type' => 'stream', 'title' => 'Get Lucky', 'uploaderName' => 'Daft Punk', 'duration' => 370],
            ]]),
        ]);

        Livewire::withQueryParams(['name' => 'daft punk'])
            ->test(Artist::class)
            ->assertDontSee('Get Lucky')
            ->call('load')
            ->assertSeeHtml('src="'.self::PHOTO.'"')
            ->assertSee('Dúo francés de música electrónica')
            ->assertSee('Daft Punk fue un dúo de música electrónica formado en París.')
            ->assertSee('seguidores')
            ->assertSee('Justice')
            ->assertSee('Get Lucky');
    }

    public function test_artist_page_still_lists_the_songs_when_no_source_knows_the_artist(): void
    {
        Http::fake([
            'deezer.test/*' => Http::response('', 503),
            'wiki.test/*' => Http::response('', 503),
            'piped.test/search*' => Http::response(['items' => [
                ['url' => '/watch?v=4D7u5KF7SP8', 'type' => 'stream', 'title' => 'Get Lucky', 'uploaderName' => 'Daft Punk', 'duration' => 370],
            ]]),
        ]);

        Livewire::withQueryParams(['name' => 'Daft Punk'])
            ->test(Artist::class)
            ->call('load')
            ->assertSee('Get Lucky')
            ->assertSee('Todavía no hay una biografía disponible');
    }

    public function test_artist_page_hides_excluded_songs_and_reports_an_unreachable_catalog(): void
    {
        ExcludedTrack::create(['video_id' => '4D7u5KF7SP8', 'title' => 'Get Lucky', 'artist' => 'Daft Punk', 'thumbnail_url' => 'https://i.ytimg.com/vi/4D7u5KF7SP8/hqdefault.jpg']);
        Http::fake([
            'deezer.test/*' => Http::response(['data' => []]),
            'wiki.test/*' => Http::response(['query' => ['pages' => []]]),
            'piped.test/search*' => Http::sequence()
                ->push(['items' => [
                    ['url' => '/watch?v=4D7u5KF7SP8', 'type' => 'stream', 'title' => 'Get Lucky', 'uploaderName' => 'Daft Punk', 'duration' => 370],
                    ['url' => '/watch?v=FGBhQbmPwH8', 'type' => 'stream', 'title' => 'One More Time', 'uploaderName' => 'Daft Punk', 'duration' => 320],
                ]])
                ->whenEmpty(Http::response('', 502)),
        ]);

        Livewire::withQueryParams(['name' => 'Daft Punk'])
            ->test(Artist::class)
            ->call('load')
            ->assertSee('One More Time')
            ->assertDontSee('Get Lucky');

        Livewire::withQueryParams(['name' => 'Otro Artista'])
            ->test(Artist::class)
            ->call('load')
            ->assertSee('No se pudieron cargar las canciones ahora mismo.');
    }

    public function test_among_artists_with_the_same_name_the_most_followed_one_wins(): void
    {
        Http::fake([
            'deezer.test/search/artist*' => Http::response(['data' => [
                $this->deezerArtist(1, 'Bad Bunny', 51),
                $this->deezerArtist(2, 'Bad Bunny', 8_078_342),
                $this->deezerArtist(3, 'Bad Bunny Tribute', 99_000_000),
            ]]),
            'deezer.test/artist/2/related*' => Http::response(['data' => []]),
            'wiki.test/*' => Http::response(['query' => ['pages' => []]]),
        ]);

        $info = app(ArtistInfoService::class)->find('bad bunny');

        $this->assertSame('Bad Bunny', $info['name']);
        $this->assertSame(8_078_342, $info['fans']);
    }

    public function test_the_biography_skips_articles_that_are_not_about_music(): void
    {
        Http::fake([
            'deezer.test/search/artist*' => Http::response(['data' => [$this->deezerArtist(5, 'Queen', 12_000_000)]]),
            'deezer.test/artist/5/related*' => Http::response(['data' => []]),
            'wiki.test/w/api.php*' => Http::response($this->wikipedia([
                ['title' => 'Queen (película)', 'description' => 'película de 2014', 'extract' => 'Queen es una película india de comedia dramática.'],
                ['title' => 'Queen', 'description' => 'banda británica de rock', 'extract' => 'Queen es una banda británica de rock formada en 1970.'],
                ['title' => 'Reina Isabel', 'description' => 'cantante', 'extract' => 'Cantante que no se llama igual.'],
            ])),
        ]);

        $info = app(ArtistInfoService::class)->find('Queen');

        $this->assertSame('Queen es una banda británica de rock formada en 1970.', $info['bio']);
        $this->assertSame('https://wiki.test/wiki/Queen', $info['bio_url']);
    }

    public function test_an_empty_profile_is_asked_again_and_a_found_one_is_cached(): void
    {
        Http::fake([
            'deezer.test/search/artist*' => Http::sequence()
                ->push('', 503)
                ->push(['data' => [$this->deezerArtist(27, 'Daft Punk', 4_500_000)]]),
            'deezer.test/artist/27/related*' => Http::response(['data' => []]),
            'wiki.test/*' => Http::response('', 503),
        ]);

        $service = app(ArtistInfoService::class);

        $this->assertNull($service->find('Daft Punk')['photo_url']);
        $this->assertSame(self::PHOTO, $service->find('Daft Punk')['photo_url']);
        $this->assertSame(self::PHOTO, $service->find('Daft Punk')['photo_url']);

        Http::assertSentCount(5); // 2 intentos fallidos + Deezer, parecidos y Wikipedia; la tercera consulta sale de la caché
    }

    /**
     * @return array<string, mixed>
     */
    private function deezerArtist(int $id, string $name, int $fans): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'picture_medium' => self::PHOTO,
            'picture_xl' => self::PHOTO,
            'nb_album' => 12,
            'nb_fan' => $fans,
        ];
    }

    /**
     * Respuesta de búsqueda de Wikipedia con las páginas en el orden dado.
     *
     * @param  list<array{title: string, description: string, extract: string}>  $pages
     * @return array<string, mixed>
     */
    private function wikipedia(array $pages): array
    {
        return ['query' => ['pages' => array_map(fn (array $page, int $index) => [
            ...$page,
            'index' => $index + 1,
            'fullurl' => 'https://wiki.test/wiki/'.str_replace(' ', '_', $page['title']),
        ], $pages, array_keys($pages))]];
    }
}
