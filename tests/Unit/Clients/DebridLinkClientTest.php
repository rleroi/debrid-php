<?php

declare(strict_types=1);

namespace Tests\Unit\Clients;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use RLeroi\Debrid\Clients\DebridLinkClient;
use RLeroi\Debrid\Exceptions\DebridException;
use InvalidArgumentException;

class DebridLinkClientTest extends TestCase
{
    private const TEST_TOKEN = 'test-token';
    private const TEST_MAGNET = 'magnet:?xt=urn:btih:34FF1FAE9661D72152FB1FC31E27C15297072654&dn=test+torrent';
    private const TEST_HASH = '34ff1fae9661d72152fb1fc31e27c15297072654';

    private DebridLinkClient $client;
    private ClientInterface $mockHttp;

    protected function setUp(): void
    {
        $this->mockHttp = Mockery::mock(ClientInterface::class);
        $this->client = new DebridLinkClient(self::TEST_TOKEN, $this->mockHttp);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructorWithToken(): void
    {
        $client = new DebridLinkClient(self::TEST_TOKEN);
        $this->assertInstanceOf(DebridLinkClient::class, $client);
    }

    public function testConstructorWithoutToken(): void
    {
        $client = new DebridLinkClient(null);
        $this->assertInstanceOf(DebridLinkClient::class, $client);
    }

    public function testSetToken(): void
    {
        $client = new DebridLinkClient(null, $this->mockHttp);
        $client->setToken(self::TEST_TOKEN);
        
        // Test that we can make a request after setting token
        // Mock checkcached response - torrent not cached
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn(new Response(200, [], json_encode(['data' => []])));

        $result = $client->getCachedFiles(self::TEST_MAGNET);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSetTokenWithEmptyTokenThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token cannot be empty');
        
        $this->client->setToken('');
    }

    public function testRequestWithoutTokenThrowsException(): void
    {
        $client = new DebridLinkClient(null, $this->mockHttp);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You must set the token before calling this method');
        
        $client->getCachedFiles(self::TEST_MAGNET);
    }

    public function testRequestWithApiErrorThrowsException(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode(['error' => 'API error'])));

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('API error');
        
        $this->client->addMagnet(self::TEST_MAGNET);
    }

    public function testRequestWithInvalidJsonThrowsException(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], 'invalid json'));

        $this->expectException(\JsonException::class);
        
        $this->client->getCachedFiles(self::TEST_MAGNET);
    }

    public function testExtractHashFromMagnet(): void
    {
        // Mock checkcached response - torrent not cached
        $checkCachedResponse = new Response(200, [], json_encode([
            'data' => []
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn($checkCachedResponse);

        $result = $this->client->getCachedFiles(self::TEST_MAGNET);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExtractHashFromInvalidMagnetThrowsException(): void
    {
        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Invalid magnet link: Could not extract hash');
        
        $this->client->getCachedFiles('invalid-magnet-link');
    }

    public function testGetCachedFilesWithCachedTorrent(): void
    {
        // Mock checkcached response - torrent is cached
        $checkCachedResponse = new Response(200, [], json_encode([
            'data' => [
                'files' => [
                    ['path' => '/test/file1.mp4', 'size' => 1024],
                    ['path' => '/test/file2.mp4', 'size' => 2048],
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn($checkCachedResponse);

        $result = $this->client->getCachedFiles(self::TEST_MAGNET);
        
        $this->assertEquals([
            '/test/file1.mp4',
            '/test/file2.mp4'
        ], $result);
    }

    public function testGetCachedFilesWithUncachedTorrent(): void
    {
        // Mock checkcached response - torrent not cached
        $checkCachedResponse = new Response(200, [], json_encode([
            'data' => []
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn($checkCachedResponse);

        $result = $this->client->getCachedFiles(self::TEST_MAGNET);
        
        $this->assertEmpty($result);
    }

    public function testIsFileCachedReturnsTrueWhenFileExists(): void
    {
        // Mock checkcached response - torrent is cached
        $checkCachedResponse = new Response(200, [], json_encode([
            'data' => [
                'files' => [
                    ['path' => '/test/file1.mp4', 'size' => 1024],
                    ['path' => '/test/file2.mp4', 'size' => 2048],
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn($checkCachedResponse);

        $result = $this->client->isFileCached(self::TEST_MAGNET, '/test/file1.mp4');
        
        $this->assertTrue($result);
    }

    public function testIsFileCachedReturnsFalseWhenFileDoesNotExist(): void
    {
        // Mock checkcached response - torrent is cached
        $checkCachedResponse = new Response(200, [], json_encode([
            'data' => [
                'files' => [
                    ['path' => '/test/file1.mp4', 'size' => 1024],
                    ['path' => '/test/file2.mp4', 'size' => 2048],
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn($checkCachedResponse);

        $result = $this->client->isFileCached(self::TEST_MAGNET, '/test/nonexistent.mp4');
        
        $this->assertFalse($result);
    }

    public function testGetLinkWithCachedTorrent(): void
    {
        // Mock checkcached response - torrent is cached
        $checkCachedResponse = new Response(200, [], json_encode([
            'data' => [
                'files' => [
                    ['path' => '/test/file1.mp4', 'id' => 'file-1', 'size' => 1024],
                    ['path' => '/test/file2.mp4', 'id' => 'file-2', 'size' => 2048],
                ]
            ]
        ]));

        // Mock download response
        $downloadResponse = new Response(200, [], json_encode([
            'data' => ['download_link' => 'https://example.com/download/file1.mp4']
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn($checkCachedResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/download', Mockery::any())
            ->andReturn($downloadResponse);

        $result = $this->client->getLink(self::TEST_MAGNET, '/test/file1.mp4');
        
        $this->assertEquals('https://example.com/download/file1.mp4', $result);
    }

    public function testGetLinkWithUncachedTorrent(): void
    {
        // Mock checkcached response - torrent not cached
        $checkCachedResponse = new Response(200, [], json_encode([
            'data' => []
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn($checkCachedResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Torrent is not cached. Please add it first using addMagnet().');
        
        $this->client->getLink(self::TEST_MAGNET, '/test/file1.mp4');
    }

    public function testGetLinkThrowsExceptionWhenFileNotFound(): void
    {
        // Mock checkcached response - torrent is cached
        $checkCachedResponse = new Response(200, [], json_encode([
            'data' => [
                'files' => [
                    ['path' => '/test/file1.mp4', 'id' => 'file-1', 'size' => 1024],
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn($checkCachedResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('File not found: /test/nonexistent.mp4');
        
        $this->client->getLink(self::TEST_MAGNET, '/test/nonexistent.mp4');
    }

    public function testAddMagnet(): void
    {
        $response = new Response(200, [], json_encode([
            'data' => ['torrent_id' => 'torrent-123']
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/add', Mockery::any())
            ->andReturn($response);

        $result = $this->client->addMagnet(self::TEST_MAGNET);
        
        $this->assertEquals('torrent-123', $result);
    }

    public function testAddMagnetThrowsExceptionWhenNoTorrentIdReturned(): void
    {
        $response = new Response(200, [], json_encode([
            'data' => []
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/add', Mockery::any())
            ->andReturn($response);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Magnet cannot be added');
        
        $this->client->addMagnet(self::TEST_MAGNET);
    }

    public function testGetLinkThrowsExceptionWhenDownloadLinkNotReturned(): void
    {
        // Mock checkcached response - torrent is cached
        $checkCachedResponse = new Response(200, [], json_encode([
            'data' => [
                'files' => [
                    ['path' => '/test/file1.mp4', 'id' => 'file-1', 'size' => 1024],
                ]
            ]
        ]));

        // Mock download response - no download link
        $downloadResponse = new Response(200, [], json_encode([
            'data' => []
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/check', Mockery::any())
            ->andReturn($checkCachedResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/download', Mockery::any())
            ->andReturn($downloadResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Failed to get download link');
        
        $this->client->getLink(self::TEST_MAGNET, '/test/file1.mp4');
    }
}
