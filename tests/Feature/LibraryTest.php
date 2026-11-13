<?php

namespace Tests\Feature;

use App\Livewire\Favorites;
use App\Livewire\Library;
use App\Livewire\Player;
use App\Livewire\Profile;
use App\Livewire\Recap;
use App\Livewire\Search;
use App\Livewire\ShowPlaylist;
use App\Models\ExcludedTrack;
use App\Models\Favorite;
use App\Models\MonthlyPlay;
use App\Models\OfflineTrack;
use App\Models\Play;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private const TRACK = [
        'video_id' => '4D7u5KF7SP8',
        'title' => 'Get Lucky',
        'artist' => 'Daft Punk',
        'thumbnail_url' => 'https://yt3.googleusercontent.com/abc=w544-h544-l90-rw',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'music.piped.instances' => ['https://piped.test'],
            'music.innertube.enabled' => false,
            'music.charts.genres' => ['rock' => ['name' => 'Rock', 'tagline' => 'Guitarras', 'id' => 21, 'hue' => 8]],
        ]);
        Http::preventStrayRequests();
    }

    public function test_pages_render_inside_the_layout_with_the_persistent_player(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $playlist = Playlist::create(['name' => 'Para correr']);

        $this->get('/')->assertOk()->assertSee('Sugerencias de hoy')->assertSee('data-player', false);
        $this->get('/search')->assertOk()->assertSee('Buscar')->assertSee('data-player', false);
        $this->get('/library')->assertOk()->assertSee('Tu biblioteca')->assertSee('data-player', false);
        $this->get('/favorites')->assertOk()->assertSee('Favoritos')->assertSee('data-player', false);
        $this->get('/recap')->assertOk()->assertSee('Resumen mensual')->assertSee('data-player', false);
        $this->get('/profile')->assertOk()->assertSee('Perfil y apariencia')->assertSee('data-player', false);
        $this->get('/new')->assertOk()->assertSee('Novedades')->assertSee('data-player', false);
        $this->get('/genres/rock')->assertOk()->assertSee('Guitarras')->assertSee('data-player', false);
        $this->get('/artist?name=Daft%20Punk')->assertOk()->assertSee('Daft Punk')->assertSee('data-player', false);
        $this->get("/playlists/{$playlist->id}")->assertOk()->assertSee('Para correr')->assertSee('data-player', false);
        $this->get('/playlists/999')->assertNotFound();
        $this->get('/genres/polka')->assertNotFound();
        $this->get('/artist')->assertNotFound();
    }

    public function test_search_page_lists_results_and_toggles_favorites(): void
    {
        Http::fake(['piped.test/search*' => Http::response(['items' => [
            ['url' => '/watch?v=4D7u5KF7SP8', 'type' => 'stream', 'title' => 'Get Lucky', 'uploaderName' => 'Daft Punk', 'duration' => 370],
        ]])]);

        Livewire::test(Search::class)
            ->set('query', 'get lucky')
            ->assertSee('Get Lucky')
            ->assertSee('6:10')
            ->call('toggleFavorite', self::TRACK)
            ->assertDispatched('favorite-changed', videoId: '4D7u5KF7SP8', favorite: true);

        $this->assertDatabaseHas('favorites', self::TRACK);
    }

    public function test_search_page_reports_unreachable_instances(): void
    {
        Http::fake(['*' => Http::response('', 502)]);

        Livewire::test(Search::class)
            ->set('query', 'get lucky')
            ->assertSee('No se pudo completar la búsqueda');
    }

    public function test_favorites_reject_malformed_tracks(): void
    {
        Livewire::test(Search::class)
            ->call('toggleFavorite', ['video_id' => '<script>', 'title' => 'x'])
            ->assertHasErrors('video_id');

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_library_creates_playlists_and_ignores_duplicate_songs(): void
    {
        $library = Livewire::test(Library::class)
            ->set('newPlaylist', '  Para correr ')
            ->call('createPlaylist')
            ->assertSet('newPlaylist', '')
            ->assertSee('Para correr')
            ->assertSee('Vacía');

        $playlist = Playlist::sole();

        $library
            ->call('addToPlaylist', $playlist->id, self::TRACK)
            ->call('addToPlaylist', $playlist->id, self::TRACK) // duplicado: se ignora
            ->assertSee('1 canción');

        $this->assertSame(1, $playlist->tracks()->count());
    }

    public function test_library_rejects_playlists_without_a_name(): void
    {
        Livewire::test(Library::class)
            ->set('newPlaylist', '   ')
            ->call('createPlaylist')
            ->assertSee('Ponle un nombre a la playlist.');

        $this->assertDatabaseCount('playlists', 0);
    }

    public function test_library_cards_show_up_to_four_covers_per_playlist(): void
    {
        $long = Playlist::create(['name' => 'Larga']);
        $short = Playlist::create(['name' => 'Corta']);

        foreach (range(1, 5) as $number) {
            $long->addTrack($this->numberedTrack($number));
        }
        $short->addTrack($this->numberedTrack(9));

        Livewire::test(Library::class)->assertViewHas('collections', fn ($collections) => $collections->firstWhere('name', 'Larga')->tracks->pluck('title')->all() === ['Song 1', 'Song 2', 'Song 3', 'Song 4']
            && $collections->firstWhere('name', 'Larga')->tracks_count === 5
            && $collections->firstWhere('name', 'Corta')->tracks->pluck('title')->all() === ['Song 9']);
    }

    public function test_playlist_page_lists_renames_and_removes_songs(): void
    {
        $playlist = Playlist::create(['name' => 'Para correr']);
        $playlist->addTrack(self::TRACK);

        Livewire::test(ShowPlaylist::class, ['playlist' => $playlist])
            ->assertSee('Get Lucky')
            ->set('name', '  Para nadar ')
            ->call('rename')
            ->assertSet('name', 'Para nadar')
            ->call('removeFromPlaylist', $playlist->tracks()->sole()->id)
            ->assertDontSee('Get Lucky')
            ->assertSee('Esta playlist está vacía.');

        $this->assertSame('Para nadar', $playlist->refresh()->name);
        $this->assertDatabaseCount('playlist_tracks', 0);
    }

    public function test_playlist_page_keeps_the_name_when_the_new_one_is_empty(): void
    {
        $playlist = Playlist::create(['name' => 'Para correr']);

        Livewire::test(ShowPlaylist::class, ['playlist' => $playlist])
            ->set('name', '   ')
            ->call('rename')
            ->assertSee('La playlist necesita un nombre.');

        $this->assertSame('Para correr', $playlist->refresh()->name);
    }

    public function test_playlist_page_does_not_remove_songs_of_another_playlist(): void
    {
        $playlist = Playlist::create(['name' => 'Para correr']);
        $other = Playlist::create(['name' => 'Otra']);
        $other->addTrack(self::TRACK);

        Livewire::test(ShowPlaylist::class, ['playlist' => $playlist])
            ->call('removeFromPlaylist', $other->tracks()->sole()->id);

        $this->assertSame(1, $other->tracks()->count());
    }

    public function test_deleting_a_playlist_returns_to_the_library(): void
    {
        $playlist = Playlist::create(['name' => 'Para correr']);
        $playlist->addTrack(self::TRACK);

        Livewire::test(ShowPlaylist::class, ['playlist' => $playlist])
            ->call('deletePlaylist')
            ->assertRedirect(route('library'));

        $this->assertDatabaseCount('playlists', 0);
        $this->assertDatabaseCount('playlist_tracks', 0);
    }

    public function test_favorites_page_lists_the_newest_first(): void
    {
        Favorite::create($this->numberedTrack(1));
        Favorite::create($this->numberedTrack(2));

        Livewire::test(Favorites::class)
            ->assertSeeInOrder(['2 canciones', 'Song 2', 'Song 1'])
            ->call('toggleFavorite', $this->numberedTrack(2))
            ->assertDontSee('Song 2')
            ->assertSee('Song 1');
    }

    public function test_excluded_songs_disappear_from_search_until_restored(): void
    {
        Http::fake(['piped.test/search*' => Http::response(['items' => [
            ['url' => '/watch?v=4D7u5KF7SP8', 'type' => 'stream', 'title' => 'Get Lucky', 'uploaderName' => 'Daft Punk', 'duration' => 370],
            ['url' => '/watch?v=FGBhQbmPwH8', 'type' => 'stream', 'title' => 'One More Time', 'uploaderName' => 'Daft Punk', 'duration' => 320],
        ]])]);

        $search = Livewire::test(Search::class)
            ->set('query', 'daft punk')
            ->assertSee('Get Lucky')
            ->call('excludeTrack', self::TRACK)
            ->assertDispatched('track-excluded', videoId: '4D7u5KF7SP8')
            ->assertDontSee('Get Lucky')
            ->assertSee('One More Time');

        Livewire::test(Library::class)
            ->assertSee('Canciones excluidas')
            ->assertSee('Get Lucky')
            ->call('restoreTrack', '4D7u5KF7SP8')
            ->assertDontSee('Canciones excluidas');

        $search->call('refreshLibrary')->assertSee('Get Lucky');
        $this->assertDatabaseCount('excluded_tracks', 0);
    }

    public function test_saving_an_excluded_song_as_favorite_lifts_the_exclusion(): void
    {
        ExcludedTrack::create(self::TRACK);

        Livewire::test(Player::class)->call('toggleFavorite', self::TRACK);

        $this->assertDatabaseHas('favorites', ['video_id' => '4D7u5KF7SP8']);
        $this->assertDatabaseCount('excluded_tracks', 0);
    }

    public function test_player_resolves_streams_and_reports_failures(): void
    {
        Favorite::create(self::TRACK);

        Http::fake([
            'piped.test/streams/4D7u5KF7SP8' => Http::response([
                'title' => 'Get Lucky',
                'uploader' => 'Daft Punk',
                'audioStreams' => [['url' => 'https://proxy.piped.test/videoplayback?itag=251', 'mimeType' => 'audio/webm', 'codec' => 'opus', 'bitrate' => 160000]],
            ]),
            'proxy.piped.test/*' => Http::response('', 206),
            'piped.test/streams/*' => Http::response(['error' => 'stack', 'message' => 'Sign in to confirm that you are not a bot'], 500),
        ]);

        Livewire::test(Player::class)
            ->call('resolveStream', '4D7u5KF7SP8')
            ->assertReturned(fn (array $result) => $result['ok']
                && $result['is_favorite']
                && $result['stream']['sources'][0]['url'] === 'https://proxy.piped.test/videoplayback?itag=251'
                && ! array_key_exists('instance', $result['stream']))
            ->call('resolveStream', 'dQw4w9WgXcQ')
            ->assertReturned(fn (array $result) => $result['ok'] === false && filled($result['message']))
            ->call('resolveStream', 'no-es-un-id')
            ->assertReturned(fn (array $result) => $result['ok'] === false);
    }

    public function test_search_page_links_each_artist_found_to_their_profile(): void
    {
        Http::fake(['piped.test/search*' => Http::response(['items' => [
            ['url' => '/watch?v=4D7u5KF7SP8', 'type' => 'stream', 'title' => 'Get Lucky', 'uploaderName' => 'Daft Punk', 'duration' => 370],
        ]])]);

        Livewire::test(Search::class)
            ->set('query', 'daft punk')
            ->assertSeeHtml('href="'.route('artist', ['name' => 'Daft Punk']).'"');
    }

    public function test_old_artist_catalog_links_redirect_to_the_artist_profile(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/search?artist=Daft%20Punk')
            ->assertRedirect(route('artist', ['name' => 'Daft Punk']));
    }

    public function test_profile_customizes_user_and_switches_between_themes(): void
    {
        $user = User::factory()->create(['theme' => 'piedra', 'avatar' => 'vinyl']);
        $this->actingAs($user);

        $this->assertGreaterThanOrEqual(10, count(User::THEMES));

        Livewire::test(Profile::class)
            ->call('selectTheme', 'lavanda')
            ->assertDispatched('theme-changed', theme: 'lavanda')
            ->call('selectAvatar', 'headphones')
            ->set('name', 'Melómano Onda')
            ->set('bio', 'Amante del synthwave y el jazz')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('lavanda', $user->theme);
        $this->assertSame('headphones', $user->avatar);
        $this->assertSame('Melómano Onda', $user->name);
        $this->assertSame('Amante del synthwave y el jazz', $user->bio);
    }

    public function test_monthly_recap_aggregates_most_listened_songs_and_artists(): void
    {
        Play::record(self::TRACK, 240);
        Play::record(self::TRACK, 240);
        Play::record([
            'video_id' => 'FGBhQbmPwH8',
            'title' => 'One More Time',
            'artist' => 'Daft Punk',
            'thumbnail_url' => 'https://yt3.googleusercontent.com/xyz=w544-h544-l90-rw',
        ], 320);

        $this->assertSame(2, MonthlyPlay::count());

        Livewire::test(Recap::class)
            ->assertSee('Get Lucky')
            ->assertSee('One More Time')
            ->assertSee('Daft Punk')
            ->assertSee('Canción #1 del mes');
    }

    public function test_offline_tracks_are_saved_and_removed_from_library(): void
    {
        Livewire::test(Player::class)
            ->call('markOfflineSaved', self::TRACK + ['duration' => 245], 3_500_000, 'audio/webm');

        $this->assertDatabaseHas('offline_tracks', [
            'video_id' => '4D7u5KF7SP8',
            'title' => 'Get Lucky',
            'size_bytes' => 3_500_000,
        ]);

        Livewire::test(Library::class)
            ->assertSee('Disponibles sin conexión')
            ->assertSee('Get Lucky')
            ->assertSee('3.3 MB')
            ->call('removeOfflineTrack', '4D7u5KF7SP8')
            ->assertDispatched('offline-remove-blob', videoId: '4D7u5KF7SP8');

        $this->assertSame(0, OfflineTrack::count());
    }

    /**
     * @return array{video_id: string, title: string, artist: string, thumbnail_url: string}
     */
    private function numberedTrack(int $number): array
    {
        return [
            'video_id' => sprintf('vid%08d', $number),
            'title' => "Song {$number}",
            'artist' => 'Artist',
            'thumbnail_url' => "https://yt3.googleusercontent.com/cover{$number}=w544-h544-l90-rw",
        ];
    }
}
