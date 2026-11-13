<?php

namespace Tests\Feature;

use App\Exceptions\MusicStreamingException;
use App\Services\MusicStreamingService;
use ArrayObject;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class MusicStreamingServiceTest extends TestCase
{
    private const A = 'https://piped-a.test';

    private const B = 'https://piped-b.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'music.piped.instances' => [self::A, self::B],
            'music.innertube.enabled' => false,
        ]);
        Http::preventStrayRequests();
    }

    public function test_search_normalizes_tracks_and_caches_the_result(): void
    {
        Http::fake([
            self::A.'/search*' => Http::response(['items' => [
                [
                    'url' => '/watch?v=4D7u5KF7SP8',
                    'type' => 'stream',
                    'title' => 'Get Lucky',
                    'thumbnail' => 'https://proxy.piped-a.test/abc=w120-h120-l90-rw?host=yt3.googleusercontent.com',
                    'uploaderName' => 'Daft Punk - Topic',
                    'duration' => 370,
                ],
                ['url' => '/channel/UC123', 'type' => 'channel', 'name' => 'Daft Punk'],
                ['url' => '/watch?v=bad', 'type' => 'stream', 'title' => 'ID no válido'],
            ]]),
        ]);

        $service = app(MusicStreamingService::class);

        $expected = [[
            'video_id' => '4D7u5KF7SP8',
            'title' => 'Get Lucky',
            'artist' => 'Daft Punk',
            'thumbnail_url' => 'https://yt3.googleusercontent.com/abc=w544-h544-l90-rw',
            'duration' => 370,
        ]];

        $this->assertSame($expected, $service->search('  Get   Lucky '));
        $this->assertSame($expected, $service->search('get lucky'));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['q'] === 'Get Lucky' && $request['filter'] === 'music_songs');
    }

    public function test_search_fails_over_to_the_next_instance(): void
    {
        Http::fake([
            self::A.'/*' => Http::response('Bad gateway', 502),
            self::B.'/search*' => Http::response(['items' => [
                ['url' => '/watch?v=4D7u5KF7SP8', 'type' => 'stream', 'title' => 'Get Lucky', 'uploaderName' => 'Daft Punk'],
            ]]),
        ]);

        $tracks = app(MusicStreamingService::class)->search('get lucky');

        $this->assertSame('4D7u5KF7SP8', $tracks[0]['video_id']);
        $this->assertSame('https://i.ytimg.com/vi/4D7u5KF7SP8/hqdefault.jpg', $tracks[0]['thumbnail_url']);
    }

    public function test_search_throws_when_every_instance_fails(): void
    {
        Http::fake(['*' => Http::response(['error' => 'stack', 'message' => 'Boom'], 500)]);

        try {
            app(MusicStreamingService::class)->search('get lucky');
            $this->fail('Se esperaba una MusicStreamingException.');
        } catch (MusicStreamingException $e) {
            $this->assertSame([self::A => 'Boom', self::B => 'Boom'], $e->attempts);
        }
    }

    public function test_stream_prefers_the_highest_bitrate_original_audio(): void
    {
        $expire = time() + 21600;

        Http::fake([
            self::A.'/streams/*' => Http::response([
                'title' => 'Get Lucky',
                'uploader' => 'Daft Punk',
                'duration' => 370,
                'thumbnailUrl' => 'https://proxy.piped-a.test/vi/4D7u5KF7SP8/maxresdefault.jpg?host=i.ytimg.com',
                'livestream' => false,
                'audioStreams' => [
                    ['url' => "https://proxy.piped-a.test/videoplayback?itag=140&expire={$expire}", 'mimeType' => 'audio/mp4', 'codec' => 'mp4a.40.2', 'bitrate' => 130000, 'quality' => '128 kbps'],
                    ['url' => "https://proxy.piped-a.test/videoplayback?itag=251&expire={$expire}", 'mimeType' => 'audio/webm', 'codec' => 'opus', 'bitrate' => 160000, 'quality' => '160 kbps'],
                    ['url' => "https://proxy.piped-a.test/videoplayback?itag=251&dub=1&expire={$expire}", 'mimeType' => 'audio/webm', 'codec' => 'opus', 'bitrate' => 170000, 'audioTrackType' => 'DUBBED'],
                ],
                'videoStreams' => [
                    ['url' => "https://proxy.piped-a.test/videoplayback?itag=18&expire={$expire}", 'mimeType' => 'video/mp4', 'videoOnly' => false, 'itag' => 18, 'quality' => '360p'],
                    ['url' => 'https://player.odycdn.com/x.mp4', 'mimeType' => 'video/mp4', 'videoOnly' => false, 'itag' => -1, 'quality' => 'LBRY'],
                    ['url' => "https://proxy.piped-a.test/videoplayback?itag=137&expire={$expire}", 'mimeType' => 'video/mp4', 'videoOnly' => true, 'itag' => 137],
                ],
            ]),
            'proxy.piped-a.test/videoplayback*' => Http::response('', 206),
        ]);

        $service = app(MusicStreamingService::class);
        $stream = $service->getStream('4D7u5KF7SP8');

        $this->assertSame('Get Lucky', $stream['title']);
        $this->assertSame('https://i.ytimg.com/vi/4D7u5KF7SP8/maxresdefault.jpg', $stream['thumbnail_url']);
        $this->assertSame($expire, $stream['expires_at']);
        $this->assertSame(self::A, $stream['instance']);
        $this->assertSame(
            [['opus', 'audio'], ['mp4a.40.2', 'audio'], [null, 'muxed']],
            array_map(fn (array $source) => [$source['codec'], $source['kind']], $stream['sources']),
        );

        // La segunda llamada sale de la caché: 1 petición a la API + 1 comprobación del enlace.
        $this->assertSame($stream, $service->getStream('4D7u5KF7SP8'));
        Http::assertSentCount(2);
    }

    public function test_stream_falls_back_to_muxed_video_and_skips_dead_links(): void
    {
        $muxed = fn (string $proxy) => [
            'title' => 'Get Lucky',
            'audioStreams' => [],
            'videoStreams' => [['url' => "https://{$proxy}/videoplayback?itag=18", 'mimeType' => 'video/mp4', 'videoOnly' => false, 'itag' => 18]],
        ];

        Http::fake([
            self::A.'/streams/*' => Http::response($muxed('proxy.piped-a.test')),
            self::B.'/streams/*' => Http::response($muxed('proxy.piped-b.test')),
            'proxy.piped-a.test/*' => Http::response('', 403),
            'proxy.piped-b.test/*' => Http::response('', 206),
        ]);

        $stream = app(MusicStreamingService::class)->getStream('4D7u5KF7SP8');

        $this->assertSame(self::B, $stream['instance']);
        $this->assertSame('https://proxy.piped-b.test/videoplayback?itag=18', $stream['sources'][0]['url']);
        $this->assertSame('muxed', $stream['sources'][0]['kind']);
        $this->assertGreaterThan(time(), $stream['expires_at']);
    }

    public function test_fresh_stream_bypasses_the_cache_and_avoids_the_previous_instance(): void
    {
        $body = fn (string $proxy) => [
            'title' => 'Get Lucky',
            'audioStreams' => [['url' => "https://{$proxy}/videoplayback?itag=251", 'mimeType' => 'audio/webm', 'codec' => 'opus', 'bitrate' => 160000]],
        ];

        Http::fake([
            self::A.'/streams/*' => Http::response($body('proxy.piped-a.test')),
            self::B.'/streams/*' => Http::response($body('proxy.piped-b.test')),
            'proxy.piped-*' => Http::response('', 206),
        ]);

        $service = app(MusicStreamingService::class);

        $this->assertSame(self::A, $service->getStream('4D7u5KF7SP8')['instance']);
        $this->assertSame(self::B, $service->getStream('4D7u5KF7SP8', fresh: true)['instance']);
    }

    public function test_stream_rejects_invalid_ids_without_calling_the_api(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(MusicStreamingService::class)->getStream('../../etc/passwd');
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * Con PHP-FPM en macOS la resolución DNS dual de curl tumba el worker (502),
     * así que tanto la llamada a la API como la comprobación del enlace van por IPv4.
     */
    public function test_outbound_requests_resolve_hosts_over_ipv4_only(): void
    {
        $ipResolve = $this->recordIpResolveOption();

        Http::fake([
            self::A.'/streams/*' => Http::response(['title' => 'Get Lucky', 'audioStreams' => [
                ['url' => 'https://proxy.piped-a.test/videoplayback?itag=251', 'mimeType' => 'audio/webm', 'bitrate' => 160000],
            ]]),
            'proxy.piped-a.test/*' => Http::response('', 206),
        ]);

        app(MusicStreamingService::class)->getStream('4D7u5KF7SP8');

        $this->assertSame(['v4', 'v4'], $ipResolve->getArrayCopy());
    }

    public function test_ipv4_only_resolution_can_be_disabled(): void
    {
        config(['music.piped.force_ipv4' => false]);

        $ipResolve = $this->recordIpResolveOption();

        Http::fake([self::A.'/search*' => Http::response(['items' => []])]);

        app(MusicStreamingService::class)->search('get lucky');

        $this->assertSame([null], $ipResolve->getArrayCopy());
    }

    public function test_innertube_resolves_stream_and_proxies_googlevideo_chunks_with_matching_user_agent(): void
    {
        config(['music.innertube.enabled' => true]);

        $expire = time() + 21600;
        $upstreamUrl = "https://rr2---sn-test.googlevideo.com/videoplayback?itag=251&c=ANDROID_VR&expire={$expire}";

        Http::fake([
            'www.youtube.com/youtubei/v1/player*' => Http::response([
                'playabilityStatus' => ['status' => 'OK'],
                'videoDetails' => [
                    'videoId' => '4D7u5KF7SP8',
                    'title' => 'Get Lucky',
                    'author' => 'Daft Punk - Topic',
                    'lengthSeconds' => '370',
                    'isLiveContent' => false,
                    'thumbnail' => [
                        'thumbnails' => [
                            ['url' => 'https://i.ytimg.com/vi/4D7u5KF7SP8/maxresdefault.jpg'],
                        ],
                    ],
                ],
                'streamingData' => [
                    'adaptiveFormats' => [
                        [
                            'itag' => 251,
                            'url' => $upstreamUrl,
                            'mimeType' => 'audio/webm; codecs="opus"',
                            'averageBitrate' => 160000,
                        ],
                    ],
                ],
            ]),
            'rr2---sn-test.googlevideo.com/videoplayback*' => Http::response('audio-bytes', 206, [
                'Content-Type' => 'audio/webm',
                'Content-Range' => 'bytes 0-10/1000',
            ]),
        ]);

        $stream = app(MusicStreamingService::class)->getStream('4D7u5KF7SP8');

        $this->assertSame('innertube:ANDROID_VR', $stream['instance']);
        $this->assertSame('Daft Punk', $stream['artist']);
        $this->assertStringStartsWith("/stream/4D7u5KF7SP8/0?expires={$expire}&signature=", $stream['sources'][0]['url']);
        $this->assertSame('audio/webm', $stream['sources'][0]['mime_type']);
        $this->assertSame('opus', $stream['sources'][0]['codec']);

        $response = $this->withHeaders(['Range' => 'bytes=0-10'])->get($stream['sources'][0]['url']);

        $response->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-10/1000')
            ->assertHeader('Accept-Ranges', 'bytes');
        $this->assertSame('audio-bytes', $response->getContent());

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'googlevideo.com')
            && str_contains($request->header('User-Agent')[0] ?? '', 'com.google.android.apps.youtube.vr.oculus'));
    }

    public function test_stream_falls_back_to_piped_when_innertube_fails(): void
    {
        config(['music.innertube.enabled' => true]);

        Http::fake([
            'www.youtube.com/youtubei/v1/player*' => Http::response([
                'playabilityStatus' => [
                    'status' => 'LOGIN_REQUIRED',
                    'reason' => "Sign in to confirm that you're not a bot",
                ],
            ]),
            self::A.'/streams/*' => Http::response([
                'title' => 'Get Lucky',
                'audioStreams' => [
                    ['url' => 'https://proxy.piped-a.test/videoplayback?itag=251', 'mimeType' => 'audio/webm', 'codec' => 'opus', 'bitrate' => 160000],
                ],
            ]),
            'proxy.piped-a.test/*' => Http::response('', 206),
        ]);

        $stream = app(MusicStreamingService::class)->getStream('4D7u5KF7SP8');

        $this->assertSame(self::A, $stream['instance']);
        $this->assertSame('https://proxy.piped-a.test/videoplayback?itag=251', $stream['sources'][0]['url']);
    }

    public function test_search_paginates_nextpage_beyond_twenty_results(): void
    {
        $pageOne = [];
        $pageTwo = [];

        for ($i = 1; $i <= 20; $i++) {
            $id = sprintf('videoA%05d', $i);
            $pageOne[] = ['url' => "/watch?v={$id}", 'type' => 'stream', 'title' => "Song {$i}", 'uploaderName' => 'Daft Punk', 'duration' => 200];
        }

        for ($i = 21; $i <= 40; $i++) {
            $id = sprintf('videoB%05d', $i);
            $pageTwo[] = ['url' => "/watch?v={$id}", 'type' => 'stream', 'title' => "Song {$i}", 'uploaderName' => 'Daft Punk', 'duration' => 210];
        }

        Http::fake([
            self::A.'/search*' => Http::response(['items' => $pageOne, 'nextpage' => 'token-page-2']),
            self::A.'/nextpage/search*' => Http::response(['items' => $pageTwo, 'nextpage' => null]),
        ]);

        $tracks = app(MusicStreamingService::class)->search('Daft Punk');

        $this->assertCount(40, $tracks);
        $this->assertSame('videoA00001', $tracks[0]['video_id']);
        $this->assertSame('videoB00040', $tracks[39]['video_id']);
    }

    public function test_artist_tracks_prioritizes_matching_artist(): void
    {
        Http::fake([
            self::A.'/search*' => Http::response(['items' => [
                ['url' => '/watch?v=other000001', 'type' => 'stream', 'title' => 'Cover Version', 'uploaderName' => 'Random Cover Band', 'duration' => 180],
                ['url' => '/watch?v=daft0000001', 'type' => 'stream', 'title' => 'One More Time', 'uploaderName' => 'Daft Punk - Topic', 'duration' => 320],
            ]]),
        ]);

        $tracks = app(MusicStreamingService::class)->artistTracks('Daft Punk');

        $this->assertCount(2, $tracks);
        $this->assertSame('daft0000001', $tracks[0]['video_id']);
        $this->assertSame('other000001', $tracks[1]['video_id']);
    }

    /**
     * Registra la opción `force_ip_resolve` de cada petición saliente.
     *
     * @return ArrayObject<int, string|null>
     */
    private function recordIpResolveOption(): ArrayObject
    {
        $recorded = new ArrayObject;

        Http::globalMiddleware(fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler, $recorded) {
            $recorded[] = $options['force_ip_resolve'] ?? null;

            return $handler($request, $options);
        });

        return $recorded;
    }

    public function test_stream_proxy_rejects_urls_without_a_valid_signature(): void
    {
        $this->get('/stream/4D7u5KF7SP8/0')->assertForbidden();
        $this->get('/stream/4D7u5KF7SP8/0?expires='.(time() + 600).'&signature=falsa')->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_lookup_many_returns_the_first_song_of_each_query(): void
    {
        Http::fake([self::A.'/search*' => fn (Request $request) => Http::response(['items' => match ($request['q']) {
            'get lucky daft punk' => [
                ['url' => '/channel/UC123', 'type' => 'channel', 'name' => 'Daft Punk'],
                ['url' => '/watch?v=4D7u5KF7SP8', 'type' => 'stream', 'title' => 'Get Lucky', 'uploaderName' => 'Daft Punk - Topic', 'duration' => 370],
                ['url' => '/watch?v=FGBhQbmPwH8', 'type' => 'stream', 'title' => 'One More Time', 'uploaderName' => 'Daft Punk', 'duration' => 320],
            ],
            default => [],
        }])]);

        $found = app(MusicStreamingService::class)->lookupMany([
            'lucky' => '  get lucky   daft punk ',
            'nada' => 'una canción que no existe',
        ]);

        $this->assertSame(['lucky', 'nada'], array_keys($found));
        $this->assertSame('4D7u5KF7SP8', $found['lucky']['video_id']);
        $this->assertSame('Daft Punk', $found['lucky']['artist']);
        $this->assertNull($found['nada']);
        Http::assertSent(fn (Request $request) => $request['filter'] === 'music_songs');
    }

    public function test_lookup_many_asks_the_next_instance_for_unanswered_queries(): void
    {
        Http::fake([
            self::A.'/search*' => Http::response('Bad Gateway', 502),
            self::B.'/search*' => Http::response(['items' => [
                ['url' => '/watch?v=4D7u5KF7SP8', 'type' => 'stream', 'title' => 'Get Lucky', 'uploaderName' => 'Daft Punk', 'duration' => 370],
            ]]),
        ]);

        $found = app(MusicStreamingService::class)->lookupMany(['lucky' => 'get lucky']);

        $this->assertSame('4D7u5KF7SP8', $found['lucky']['video_id']);
    }

    public function test_lookup_many_returns_null_when_no_instance_answers(): void
    {
        Http::fake([
            self::A.'/search*' => Http::response('Bad Gateway', 502),
            self::B.'/search*' => fn () => throw new ConnectionException('Boom'),
        ]);

        $this->assertSame(['lucky' => null], app(MusicStreamingService::class)->lookupMany(['lucky' => 'get lucky']));
    }
}
