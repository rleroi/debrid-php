<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Tests\Unit\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use JsonException;
use Mockery;
use PHPUnit\Framework\TestCase;
use RLeroi\Debrid\Clients\RealDebridClient;
use RLeroi\Debrid\Exceptions\DebridException;

final class RealDebridClientTest extends TestCase
{
    private const TEST_MAGNET = 'magnet:?xt=urn:btih:1234567890abcdef1234567890abcdef12345678&dn=test&tr=udp%3A%2F%2Ftracker.example.com%3A1337';
    
    private RealDebridClient $client;
    private $mockHttp;

    protected function setUp(): void
    {
        $this->mockHttp = Mockery::mock(Client::class);
        $this->client = new RealDebridClient('test-token', $this->mockHttp);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructorWithToken(): void
    {
        $client = new RealDebridClient('test-token');
        $this->assertInstanceOf(RealDebridClient::class, $client);
    }

    public function testConstructorWithoutToken(): void
    {
        $client = new RealDebridClient(null);
        $this->assertInstanceOf(RealDebridClient::class, $client);
    }

    public function testSetToken(): void
    {
        $client = new RealDebridClient(null, $this->mockHttp);
        $client->setToken('new-token');
        
        // Mock a response to test that the token is set correctly
        $response = new Response(200, [], json_encode([
            'error' => 'bad_token'
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn($response);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('bad_token');
        $client->addMagnet(self::TEST_MAGNET);
    }

    public function testRequestThrowsExceptionWhenNoToken(): void
    {
        $client = new RealDebridClient(null, $this->mockHttp);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You must set the token before calling this method');
        
        $client->addMagnet(self::TEST_MAGNET);
    }

    public function testRequestThrowsExceptionOnApiError(): void
    {
        $errorResponse = new Response(200, [], json_encode([
            'error' => 'API Error'
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn($errorResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('API Error');
        
        $this->client->addMagnet(self::TEST_MAGNET);
    }

    public function testRequestThrowsExceptionOnInvalidJson(): void
    {
        $invalidResponse = new Response(200, [], 'invalid json');

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn($invalidResponse);

        $this->expectException(JsonException::class);
        
        $this->client->addMagnet(self::TEST_MAGNET);
    }

    public function testGetCachedFilesReturnsFilePaths(): void
    {
        // Mock GET torrents response (empty list - torrent not found)
        $torrentsResponse = new Response(200, [], json_encode([]));
        
        // Mock addMagnet response
        $addMagnetResponse = new Response(200, [], json_encode(['id' => 'torrent-123']));
        
        // Mock torrent info response
        $torrentInfoResponse = new Response(200, [], json_encode([
            'id' => 'torrent-123',
            'status' => 'downloaded',
            'files' => [
                ['id' => 1, 'path' => '/test/file1.mp4', 'bytes' => 1024],
                ['id' => 2, 'path' => '/test/file2.mp4', 'bytes' => 2048],
                ['id' => 3, 'path' => '/test/subfolder/file3.mp4', 'bytes' => 3072]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents', Mockery::any())
            ->andReturn($torrentsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/addMagnet', Mockery::any())
            ->andReturn($addMagnetResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/info/torrent-123', Mockery::any())
            ->andReturn($torrentInfoResponse);

        $result = $this->client->getCachedFiles(self::TEST_MAGNET);
        
        $this->assertEquals([
            '/test/file1.mp4',
            '/test/file2.mp4',
            '/test/subfolder/file3.mp4'
        ], $result);
    }

    public function testGetCachedFilesThrowsExceptionWhenTorrentNotReady(): void
    {
        // Mock GET torrents response (empty list - torrent not found)
        $torrentsResponse = new Response(200, [], json_encode([]));
        
        // Mock addMagnet response
        $addMagnetResponse = new Response(200, [], json_encode(['id' => 'torrent-123']));
        
        // Mock torrent info response - torrent not ready
        $torrentInfoResponse = new Response(200, [], json_encode([
            'id' => 'torrent-123',
            'status' => 'downloading',
            'files' => [
                ['id' => 1, 'path' => '/test/file1.mp4', 'bytes' => 1024]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents', Mockery::any())
            ->andReturn($torrentsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/addMagnet', Mockery::any())
            ->andReturn($addMagnetResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/info/torrent-123', Mockery::any())
            ->andReturn($torrentInfoResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Torrent is not ready. Current status: downloading');
        
        $this->client->getCachedFiles(self::TEST_MAGNET);
    }

    public function testIsFileCachedReturnsTrueWhenFileExists(): void
    {
        // Mock GET torrents response (empty list - torrent not found)
        $torrentsResponse = new Response(200, [], json_encode([]));
        
        // Mock addMagnet response
        $addMagnetResponse = new Response(200, [], json_encode(['id' => 'torrent-123']));
        
        // Mock torrent info response
        $torrentInfoResponse = new Response(200, [], json_encode([
            'id' => 'torrent-123',
            'status' => 'downloaded',
            'files' => [
                ['id' => 1, 'path' => '/test/file1.mp4', 'bytes' => 1024],
                ['id' => 2, 'path' => '/test/file2.mp4', 'bytes' => 2048]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents', Mockery::any())
            ->andReturn($torrentsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/addMagnet', Mockery::any())
            ->andReturn($addMagnetResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/info/torrent-123', Mockery::any())
            ->andReturn($torrentInfoResponse);

        $result = $this->client->isFileCached(self::TEST_MAGNET, '/test/file1.mp4');
        
        $this->assertTrue($result);
    }

    public function testIsFileCachedReturnsFalseWhenFileDoesNotExist(): void
    {
        // Mock GET torrents response (empty list - torrent not found)
        $torrentsResponse = new Response(200, [], json_encode([]));
        
        // Mock addMagnet response
        $addMagnetResponse = new Response(200, [], json_encode(['id' => 'torrent-123']));
        
        // Mock torrent info response
        $torrentInfoResponse = new Response(200, [], json_encode([
            'id' => 'torrent-123',
            'status' => 'downloaded',
            'files' => [
                ['id' => 1, 'path' => '/test/file1.mp4', 'bytes' => 1024],
                ['id' => 2, 'path' => '/test/file2.mp4', 'bytes' => 2048]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents', Mockery::any())
            ->andReturn($torrentsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/addMagnet', Mockery::any())
            ->andReturn($addMagnetResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/info/torrent-123', Mockery::any())
            ->andReturn($torrentInfoResponse);

        $result = $this->client->isFileCached(self::TEST_MAGNET, '/test/nonexistent.mp4');
        
        $this->assertFalse($result);
    }

    public function testGetLinkReturnsDownloadLink(): void
    {
        // Mock GET torrents response (empty list - torrent not found)
        $torrentsResponse = new Response(200, [], json_encode([]));
        
        // Mock addMagnet response
        $addMagnetResponse = new Response(200, [], json_encode(['id' => 'torrent-123']));
        
        // Mock initial torrent info response
        $torrentInfoResponse = new Response(200, [], json_encode([
            'id' => 'torrent-123',
            'status' => 'downloaded',
            'files' => [
                ['id' => 1, 'path' => '/test/file1.mp4', 'bytes' => 1024],
                ['id' => 2, 'path' => '/test/file2.mp4', 'bytes' => 2048]
            ],
            'links' => []
        ]));
        
        // Mock selectFiles response
        $selectFilesResponse = new Response(200, [], json_encode(['status' => 'success']));

        // Mock updated torrent info response (after selecting files)
        $updatedTorrentInfoResponse = new Response(200, [], json_encode([
            'id' => 'torrent-123',
            'status' => 'downloaded',
            'files' => [
                ['id' => 1, 'path' => '/test/file1.mp4', 'bytes' => 1024, 'selected' => 1],
                ['id' => 2, 'path' => '/test/file2.mp4', 'bytes' => 2048, 'selected' => 0]
            ],
            'links' => ['https://example.com/link1.mp4']
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents', Mockery::any())
            ->andReturn($torrentsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/addMagnet', Mockery::any())
            ->andReturn($addMagnetResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/info/torrent-123', Mockery::any())
            ->andReturn($torrentInfoResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/selectFiles/torrent-123', Mockery::any())
            ->andReturn($selectFilesResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/info/torrent-123', Mockery::any())
            ->andReturn($updatedTorrentInfoResponse);

        // Mock unrestrict/link response
        $unrestrictResponse = new Response(200, [], json_encode([
            'download' => 'https://example.com/unrestricted.mp4'
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'unrestrict/link', Mockery::any())
            ->andReturn($unrestrictResponse);

        $result = $this->client->getLink(self::TEST_MAGNET, '/test/file1.mp4');
        
        $this->assertEquals('https://example.com/unrestricted.mp4', $result);
    }

    public function testGetLinkThrowsExceptionWhenFileNotFound(): void
    {
        // Mock GET torrents response (empty list - torrent not found)
        $torrentsResponse = new Response(200, [], json_encode([]));
        
        // Mock addMagnet response
        $addMagnetResponse = new Response(200, [], json_encode(['id' => 'torrent-123']));
        
        // Mock torrent info response
        $torrentInfoResponse = new Response(200, [], json_encode([
            'id' => 'torrent-123',
            'status' => 'downloaded',
            'files' => [
                ['id' => 1, 'path' => '/test/file1.mp4', 'bytes' => 1024]
            ],
            'links' => ['https://example.com/link1.mp4']
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents', Mockery::any())
            ->andReturn($torrentsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/addMagnet', Mockery::any())
            ->andReturn($addMagnetResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/info/torrent-123', Mockery::any())
            ->andReturn($torrentInfoResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage("File with path '/test/nonexistent.mp4' not found in torrent");
        
        $this->client->getLink(self::TEST_MAGNET, '/test/nonexistent.mp4');
    }

    public function testGetLinkThrowsExceptionWhenTorrentNotDownloaded(): void
    {
        // Mock GET torrents response (empty list - torrent not found)
        $torrentsResponse = new Response(200, [], json_encode([]));
        
        // Mock addMagnet response
        $addMagnetResponse = new Response(200, [], json_encode(['id' => 'torrent-123']));
        
        // Mock torrent info response - torrent not downloaded
        $torrentInfoResponse = new Response(200, [], json_encode([
            'id' => 'torrent-123',
            'status' => 'downloading',
            'files' => [
                ['id' => 1, 'path' => '/test/file1.mp4', 'bytes' => 1024]
            ],
            'links' => []
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents', Mockery::any())
            ->andReturn($torrentsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/addMagnet', Mockery::any())
            ->andReturn($addMagnetResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents/info/torrent-123', Mockery::any())
            ->andReturn($torrentInfoResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Torrent is not ready. Current status: downloading');
        
        $this->client->getLink(self::TEST_MAGNET, '/test/file1.mp4');
    }

    public function testAddMagnetReturnsTorrentId(): void
    {
        // Mock GET torrents response (empty list - torrent not found)
        $torrentsResponse = new Response(200, [], json_encode([]));
        
        // Mock addMagnet response
        $addMagnetResponse = new Response(200, [], json_encode([
            'id' => 'torrent-123'
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents', Mockery::any())
            ->andReturn($torrentsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/addMagnet', Mockery::any())
            ->andReturn($addMagnetResponse);

        $result = $this->client->addMagnet(self::TEST_MAGNET);
        
        $this->assertEquals('torrent-123', $result);
    }

    public function testAddMagnetThrowsExceptionWhenNoIdReturned(): void
    {
        // Mock GET torrents response (empty list - torrent not found)
        $torrentsResponse = new Response(200, [], json_encode([]));
        
        // Mock addMagnet response (no ID returned)
        $addMagnetResponse = new Response(200, [], json_encode([
            'status' => 'success'
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'torrents', Mockery::any())
            ->andReturn($torrentsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'torrents/addMagnet', Mockery::any())
            ->andReturn($addMagnetResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Failed to add magnet: No torrent ID returned');
        
        $this->client->addMagnet(self::TEST_MAGNET);
    }
}
