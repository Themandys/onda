<?php

namespace Tests\Feature;

use App\Livewire\Player;
use App\Models\ExcludedTrack;
use App\Models\Play;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PlayerTest extends TestCase
{
    use RefreshDatabase;

    private const TRACK = [
        'video_id' => '4D7u5KF7SP8',
        'title' => 'Get Lucky',
        'artist' => 'Daft Punk',
        'thumbnail_url' => 'https://yt3.googleusercontent.com/abc=w544-h544-l90-rw',
    ];

    public function test_recording_a_play_stores_the_song_and_counts_repeats(): void
    {
        $this->freezeSecond();
        $player = Livewire::test(Player::class)->call('recordPlay', self::TRACK);

        $this->travel(10)->minutes();
        $player->call('recordPlay', self::TRACK);

        $play = Play::sole();

        $this->assertSame(self::TRACK, $play->toTrack());
        $this->assertSame(2, $play->play_count);
        $this->assertTrue($play->last_played_at->equalTo(now()));
    }

    public function test_recording_a_play_rejects_malformed_songs(): void
    {
        Livewire::test(Player::class)
            ->call('recordPlay', ['video_id' => '<script>', 'title' => 'x'])
            ->assertHasErrors('video_id');

        $this->assertDatabaseCount('plays', 0);
    }

    public function test_dj_mix_adds_songs_by_the_same_artist_and_popular_ones_that_are_not_queued_or_excluded(): void
    {
        config([
            'music.piped.instances' => ['https://piped.test'],
            'music.innertube.enabled' => false,
            'music.charts.endpoint' => 'https://charts.test',
            'music.charts.genres' => [],
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'charts.test/*' => Http::response(['feed' => ['results' => [
                ['id' => '1', 'name' => 'Hit 01', 'artistName' => 'Otro'],
                ['id' => '2', 'name' => 'Hit 02', 'artistName' => 'Otro'],
            ]]]),
            'piped.test/search*' => fn (Request $request) => Http::response(['items' => str_contains((string) $request['q'], 'Hit')
                ? [$this->item('hit0000000'.substr((string) $request['q'], 5, 1), (string) $request['q'], 'Otro')]
                : [
                    $this->item('4D7u5KF7SP8', 'Get Lucky', 'Daft Punk'),
                    $this->item('FGBhQbmPwH8', 'One More Time', 'Daft Punk'),
                    $this->item('yca6UsllwYs', 'Around the World', 'Daft Punk'),
                    $this->item('sOS9aOIXPEk', 'Instant Crush', 'Daft Punk'),
                ]]),
        ]);
        ExcludedTrack::create(['video_id' => 'sOS9aOIXPEk', 'title' => 'Instant Crush', 'artist' => 'Daft Punk', 'thumbnail_url' => 'https://i.ytimg.com/vi/sOS9aOIXPEk/hqdefault.jpg']);

        Livewire::test(Player::class)
            ->call('djMix', self::TRACK, ['FGBhQbmPwH8', 'hit00000001'])
            ->assertReturned(function (array $mix) {
                $this->assertEqualsCanonicalizing(['yca6UsllwYs', 'hit00000002'], array_column($mix, 'video_id'));
                $this->assertSame(['video_id', 'title', 'artist', 'thumbnail_url', 'duration'], array_keys($mix[0]));

                return true;
            });
    }

    public function test_dj_mix_rejects_malformed_songs(): void
    {
        Livewire::test(Player::class)
            ->call('djMix', ['video_id' => '<script>', 'title' => 'x'])
            ->assertHasErrors('video_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $videoId, string $title, string $artist): array
    {
        return ['url' => '/watch?v='.$videoId, 'type' => 'stream', 'title' => $title, 'uploaderName' => $artist, 'duration' => 200];
    }
}
