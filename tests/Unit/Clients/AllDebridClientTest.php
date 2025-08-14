<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Tests\Unit\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use JsonException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RLeroi\Debrid\Clients\AllDebridClient;
use RLeroi\Debrid\Exceptions\DebridException;

final class AllDebridClientTest extends TestCase
{
    private const TEST_MAGNET = 'magnet:?xt=urn:btih:1234567890abcdef1234567890abcdef12345678&dn=test&tr=udp%3A%2F%2Ftracker.example.com%3A1337';
    
    private AllDebridClient $client;
    private $mockHttp;

    protected function setUp(): void
    {
        $this->mockHttp = Mockery::mock(Client::class);
        $this->client = new AllDebridClient('test-token', $this->mockHttp);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructorWithToken(): void
    {
        $client = new AllDebridClient('test-token');
        $this->assertInstanceOf(AllDebridClient::class, $client);
    }

    public function testConstructorWithoutToken(): void
    {
        $client = new AllDebridClient(null);
        $this->assertInstanceOf(AllDebridClient::class, $client);
    }

    public function testSetToken(): void
    {
        $client = new AllDebridClient(null, $this->mockHttp);
        $client->setToken('new-token');
        
        // Mock a response to test that the token is set correctly
        $response = new Response(200, [], json_encode([
            'status' => 'error',
            'error' => [
                'code' => 'AUTH_BAD_APIKEY',
                'message' => 'The auth apikey is invalid'
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn($response);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('API Error (AUTH_BAD_APIKEY): The auth apikey is invalid');
        $client->addMagnet(self::TEST_MAGNET);
    }

    public function testRequestThrowsExceptionWhenNoToken(): void
    {
        $client = new AllDebridClient(null, $this->mockHttp);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You must set the token before calling this method');
        
        $client->addMagnet(self::TEST_MAGNET);
    }

    public function testRequestThrowsExceptionOnApiError(): void
    {
        $errorResponse = new Response(200, [], json_encode([
            'status' => 'error',
            'error' => [
                'code' => 'MAGNET_NO_URI',
                'message' => 'No magnet sent'
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn($errorResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('API Error (MAGNET_NO_URI): No magnet sent');
        
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
        // Mock GET magnet/status response (empty list - magnet not found)
        $magnetsResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['magnets' => []]
        ]));
        
        // Mock POST magnet/upload response
        $uploadResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    ['id' => 'magnet-123']
                ]
            ]
        ]));
        
        // Mock GET magnet/status response (magnet ready)
        $statusResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'status' => 'Ready'
                    ]
                ]
            ]
        ]));
        
        // Mock GET magnet/links response
        $linksResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'files' => [
                            ['n' => 'file1.mp4', 'link' => 'https://example.com/file1.mp4'],
                            ['n' => 'file2.mp4', 'link' => 'https://example.com/file2.mp4'],
                            ['n' => 'subfolder/file3.mp4', 'link' => 'https://example.com/file3.mp4']
                        ]
                    ]
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($magnetsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'magnet/upload', Mockery::any())
            ->andReturn($uploadResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($statusResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/links', Mockery::any())
            ->andReturn($linksResponse);

        $result = $this->client->getCachedFiles(self::TEST_MAGNET);
        
        $this->assertEquals([
            'file1.mp4',
            'file2.mp4',
            'subfolder/file3.mp4'
        ], $result);
    }

    public function testGetCachedFilesThrowsExceptionWhenMagnetNotReady(): void
    {
        // Mock GET magnet/status response (empty list - magnet not found)
        $magnetsResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['magnets' => []]
        ]));
        
        // Mock POST magnet/upload response
        $uploadResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    ['id' => 'magnet-123']
                ]
            ]
        ]));
        
        // Mock GET magnet/status response (magnet not ready)
        $statusResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'status' => 'Processing'
                    ]
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($magnetsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'magnet/upload', Mockery::any())
            ->andReturn($uploadResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($statusResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Magnet is not ready. Current status: Processing');
        
        $this->client->getCachedFiles(self::TEST_MAGNET);
    }

    public function testIsFileCachedReturnsTrueWhenFileExists(): void
    {
        // Mock GET magnet/status response (empty list - magnet not found)
        $magnetsResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['magnets' => []]
        ]));
        
        // Mock POST magnet/upload response
        $uploadResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    ['id' => 'magnet-123']
                ]
            ]
        ]));
        
        // Mock GET magnet/status response (magnet ready)
        $statusResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'status' => 'Ready'
                    ]
                ]
            ]
        ]));
        
        // Mock GET magnet/links response
        $linksResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'files' => [
                            ['n' => 'file1.mp4', 'link' => 'https://example.com/file1.mp4'],
                            ['n' => 'file2.mp4', 'link' => 'https://example.com/file2.mp4']
                        ]
                    ]
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($magnetsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'magnet/upload', Mockery::any())
            ->andReturn($uploadResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($statusResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/links', Mockery::any())
            ->andReturn($linksResponse);

        $result = $this->client->isFileCached(self::TEST_MAGNET, 'file1.mp4');
        
        $this->assertTrue($result);
    }

    public function testIsFileCachedReturnsFalseWhenFileDoesNotExist(): void
    {
        // Mock GET magnet/status response (empty list - magnet not found)
        $magnetsResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['magnets' => []]
        ]));
        
        // Mock POST magnet/upload response
        $uploadResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    ['id' => 'magnet-123']
                ]
            ]
        ]));
        
        // Mock GET magnet/status response (magnet ready)
        $statusResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'status' => 'Ready'
                    ]
                ]
            ]
        ]));
        
        // Mock GET magnet/links response
        $linksResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'files' => [
                            ['n' => 'file1.mp4', 'link' => 'https://example.com/file1.mp4'],
                            ['n' => 'file2.mp4', 'link' => 'https://example.com/file2.mp4']
                        ]
                    ]
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($magnetsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'magnet/upload', Mockery::any())
            ->andReturn($uploadResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($statusResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/links', Mockery::any())
            ->andReturn($linksResponse);

        $result = $this->client->isFileCached(self::TEST_MAGNET, 'nonexistent.mp4');
        
        $this->assertFalse($result);
    }

    public function testGetLinkReturnsDownloadLink(): void
    {
        // Mock GET magnet/status response (empty list - magnet not found)
        $magnetsResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['magnets' => []]
        ]));
        
        // Mock POST magnet/upload response
        $uploadResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    ['id' => 'magnet-123']
                ]
            ]
        ]));
        
        // Mock GET magnet/status response (magnet ready)
        $statusResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'status' => 'Ready'
                    ]
                ]
            ]
        ]));
        
        // Mock GET magnet/links response
        $linksResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'files' => [
                            ['n' => 'file1.mp4', 'link' => 'https://example.com/file1.mp4'],
                            ['n' => 'file2.mp4', 'link' => 'https://example.com/file2.mp4']
                        ]
                    ]
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($magnetsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'magnet/upload', Mockery::any())
            ->andReturn($uploadResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($statusResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/links', Mockery::any())
            ->andReturn($linksResponse);

        $result = $this->client->getLink(self::TEST_MAGNET, 'file1.mp4');
        
        $this->assertEquals('https://example.com/file1.mp4', $result);
    }

    public function testGetLinkThrowsExceptionWhenFileNotFound(): void
    {
        // Mock GET magnet/status response (empty list - magnet not found)
        $magnetsResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['magnets' => []]
        ]));
        
        // Mock POST magnet/upload response
        $uploadResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    ['id' => 'magnet-123']
                ]
            ]
        ]));
        
        // Mock GET magnet/status response (magnet ready)
        $statusResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'status' => 'Ready'
                    ]
                ]
            ]
        ]));
        
        // Mock GET magnet/links response
        $linksResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'files' => [
                            ['n' => 'file1.mp4', 'link' => 'https://example.com/file1.mp4']
                        ]
                    ]
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($magnetsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'magnet/upload', Mockery::any())
            ->andReturn($uploadResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($statusResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/links', Mockery::any())
            ->andReturn($linksResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage("File with path 'nonexistent.mp4' not found in magnet");
        
        $this->client->getLink(self::TEST_MAGNET, 'nonexistent.mp4');
    }

    public function testGetLinkThrowsExceptionWhenMagnetNotReady(): void
    {
        // Mock GET magnet/status response (empty list - magnet not found)
        $magnetsResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['magnets' => []]
        ]));
        
        // Mock POST magnet/upload response
        $uploadResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    ['id' => 'magnet-123']
                ]
            ]
        ]));
        
        // Mock GET magnet/status response (magnet not ready)
        $statusResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    [
                        'id' => 'magnet-123',
                        'status' => 'Processing'
                    ]
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($magnetsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'magnet/upload', Mockery::any())
            ->andReturn($uploadResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($statusResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Magnet is not ready. Current status: Processing');
        
        $this->client->getLink(self::TEST_MAGNET, 'file1.mp4');
    }

    public function testAddMagnetReturnsMagnetId(): void
    {
        // Mock GET magnet/status response (empty list - magnet not found)
        $magnetsResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['magnets' => []]
        ]));
        
        // Mock POST magnet/upload response
        $uploadResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    ['id' => 'magnet-123']
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($magnetsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'magnet/upload', Mockery::any())
            ->andReturn($uploadResponse);

        $result = $this->client->addMagnet(self::TEST_MAGNET);
        
        $this->assertEquals('magnet-123', $result);
    }

    public function testAddMagnetThrowsExceptionWhenNoIdReturned(): void
    {
        // Mock GET magnet/status response (empty list - magnet not found)
        $magnetsResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['magnets' => []]
        ]));
        
        // Mock POST magnet/upload response (no ID returned)
        $uploadResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'magnets' => [
                    ['status' => 'success']
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'magnet/status', Mockery::any())
            ->andReturn($magnetsResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'magnet/upload', Mockery::any())
            ->andReturn($uploadResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Failed to upload magnet: No magnet ID returned');
        
        $this->client->addMagnet(self::TEST_MAGNET);
    }
}
