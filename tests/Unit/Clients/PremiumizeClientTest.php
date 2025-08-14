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
use RLeroi\Debrid\Clients\PremiumizeClient;
use RLeroi\Debrid\Exceptions\DebridException;

final class PremiumizeClientTest extends TestCase
{
    private PremiumizeClient $client;
    private $mockHttp;

    protected function setUp(): void
    {
        $this->mockHttp = Mockery::mock(Client::class);
        $this->client = new PremiumizeClient('test-token', $this->mockHttp);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructorWithToken(): void
    {
        $client = new PremiumizeClient('test-token');
        $this->assertInstanceOf(PremiumizeClient::class, $client);
    }

    public function testConstructorWithoutToken(): void
    {
        $client = new PremiumizeClient(null);
        $this->assertInstanceOf(PremiumizeClient::class, $client);
    }

    public function testSetToken(): void
    {
        $client = new PremiumizeClient(null);
        $client->setToken('new-token');
        
        // We can't directly test the private token property, but we can test it works
        // by calling a method that requires the token
        $this->expectException(DebridException::class);
        $client->getCachedFiles('test-magnet');
    }

    public function testRequestThrowsExceptionWhenNoToken(): void
    {
        $client = new PremiumizeClient(null, $this->mockHttp);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You must set the token before calling this method');
        
        $client->getCachedFiles('test-magnet');
    }

    public function testRequestThrowsExceptionOnApiError(): void
    {
        $errorResponse = new Response(200, [], json_encode([
            'status' => 'error',
            'message' => 'API Error'
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn($errorResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('API Error');
        
        $this->client->getCachedFiles('test-magnet');
    }

    public function testRequestThrowsExceptionOnInvalidJson(): void
    {
        $invalidResponse = new Response(200, [], 'invalid json');

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn($invalidResponse);

        $this->expectException(JsonException::class);
        
        $this->client->getCachedFiles('test-magnet');
    }

    public function testGetCachedFilesReturnsEmptyArrayWhenMagnetNotCached(): void
    {
        // Mock cache check response - magnet not cached
        $cacheResponse = new Response(200, [], json_encode([
            'response' => [false]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'cache/check', Mockery::any())
            ->andReturn($cacheResponse);

        $result = $this->client->getCachedFiles('test-magnet');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetCachedFilesReturnsFilesWhenMagnetCached(): void
    {
        // Mock cache check response - magnet is cached
        $cacheResponse = new Response(200, [], json_encode([
            'response' => [true]
        ]));

        // Mock directdl response with files
        $filesResponse = new Response(200, [], json_encode([
            'content' => [
                [
                    'path' => '/test/file1.mp4',
                    'size' => 1024,
                    'stream_link' => 'https://example.com/stream1'
                ],
                [
                    'path' => '/test/file2.mp4',
                    'size' => 2048,
                    'link' => 'https://example.com/link2'
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'cache/check', Mockery::any())
            ->andReturn($cacheResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/directdl', Mockery::any())
            ->andReturn($filesResponse);

        $result = $this->client->getCachedFiles('test-magnet');
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('/test/file1.mp4', $result[0]['path']);
        $this->assertEquals(1024, $result[0]['size']);
        $this->assertEquals('/test/file2.mp4', $result[1]['path']);
        $this->assertEquals(2048, $result[1]['size']);
    }

    public function testIsFileCachedReturnsFalseWhenMagnetNotCached(): void
    {
        // Mock cache check response - magnet not cached
        $cacheResponse = new Response(200, [], json_encode([
            'response' => [false]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'cache/check', Mockery::any())
            ->andReturn($cacheResponse);

        $result = $this->client->isFileCached('test-magnet', '/test/file.mp4');
        
        $this->assertFalse($result);
    }

    public function testIsFileCachedReturnsTrueWhenFileExists(): void
    {
        // Mock cache check response - magnet is cached
        $cacheResponse = new Response(200, [], json_encode([
            'response' => [true]
        ]));

        // Mock directdl response with the specific file
        $filesResponse = new Response(200, [], json_encode([
            'content' => [
                [
                    'path' => '/test/file.mp4',
                    'size' => 1024,
                    'stream_link' => 'https://example.com/stream1'
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'cache/check', Mockery::any())
            ->andReturn($cacheResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/directdl', Mockery::any())
            ->andReturn($filesResponse);

        $result = $this->client->isFileCached('test-magnet', '/test/file.mp4');
        
        $this->assertTrue($result);
    }

    public function testIsFileCachedReturnsFalseWhenFileDoesNotExist(): void
    {
        // Mock cache check response - magnet is cached
        $cacheResponse = new Response(200, [], json_encode([
            'response' => [true]
        ]));

        // Mock directdl response with different file
        $filesResponse = new Response(200, [], json_encode([
            'content' => [
                [
                    'path' => '/test/other.mp4',
                    'size' => 1024,
                    'stream_link' => 'https://example.com/stream1'
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'cache/check', Mockery::any())
            ->andReturn($cacheResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/directdl', Mockery::any())
            ->andReturn($filesResponse);

        $result = $this->client->isFileCached('test-magnet', '/test/file.mp4');
        
        $this->assertFalse($result);
    }

    public function testGetLinkReturnsStreamLinkWhenAvailable(): void
    {
        // Mock cache check response - magnet is cached
        $cacheResponse = new Response(200, [], json_encode([
            'response' => [true]
        ]));

        // Mock directdl response with stream_link
        $filesResponse = new Response(200, [], json_encode([
            'content' => [
                [
                    'path' => '/test/file.mp4',
                    'size' => 1024,
                    'stream_link' => 'https://example.com/stream1'
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'cache/check', Mockery::any())
            ->andReturn($cacheResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/directdl', Mockery::any())
            ->andReturn($filesResponse);

        $result = $this->client->getLink('test-magnet', '/test/file.mp4');
        
        $this->assertEquals('https://example.com/stream1', $result);
    }

    public function testGetLinkReturnsLinkWhenStreamLinkNotAvailable(): void
    {
        // Mock cache check response - magnet is cached
        $cacheResponse = new Response(200, [], json_encode([
            'response' => [true]
        ]));

        // Mock directdl response with only link
        $filesResponse = new Response(200, [], json_encode([
            'content' => [
                [
                    'path' => '/test/file.mp4',
                    'size' => 1024,
                    'link' => 'https://example.com/link1'
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'cache/check', Mockery::any())
            ->andReturn($cacheResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/directdl', Mockery::any())
            ->andReturn($filesResponse);

        $result = $this->client->getLink('test-magnet', '/test/file.mp4');
        
        $this->assertEquals('https://example.com/link1', $result);
    }

    public function testGetLinkReturnsEmptyStringWhenFileNotFound(): void
    {
        // Mock cache check response - magnet is cached
        $cacheResponse = new Response(200, [], json_encode([
            'response' => [true]
        ]));

        // Mock directdl response with different file
        $filesResponse = new Response(200, [], json_encode([
            'content' => [
                [
                    'path' => '/test/other.mp4',
                    'size' => 1024,
                    'link' => 'https://example.com/link1'
                ]
            ]
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'cache/check', Mockery::any())
            ->andReturn($cacheResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/directdl', Mockery::any())
            ->andReturn($filesResponse);

        $result = $this->client->getLink('test-magnet', '/test/file.mp4');
        
        $this->assertEquals('', $result);
    }

    public function testAddMagnetReturnsTransferId(): void
    {
        $transferResponse = new Response(200, [], json_encode([
            'id' => 'transfer-123'
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/create', Mockery::any())
            ->andReturn($transferResponse);

        $result = $this->client->addMagnet('magnet:?xt=urn:btih:test');
        
        $this->assertEquals('transfer-123', $result);
    }

    public function testAddMagnetThrowsExceptionWhenNoIdReturned(): void
    {
        $transferResponse = new Response(200, [], json_encode([
            'status' => 'success'
        ]));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/create', Mockery::any())
            ->andReturn($transferResponse);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Failed to add magnet: No transfer ID returned');
        
        $this->client->addMagnet('magnet:?xt=urn:btih:test');
    }

    public function testAddMagnetClearsFilesCache(): void
    {
        // First, populate the cache by getting files
        $cacheResponse = new Response(200, [], json_encode([
            'response' => [true]
        ]));

        $filesResponse = new Response(200, [], json_encode([
            'content' => [
                [
                    'path' => '/test/file.mp4',
                    'size' => 1024,
                    'stream_link' => 'https://example.com/stream1'
                ]
            ]
        ]));

        $transferResponse = new Response(200, [], json_encode([
            'id' => 'transfer-123'
        ]));

        // First call to populate cache
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'cache/check', Mockery::any())
            ->andReturn($cacheResponse);

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/directdl', Mockery::any())
            ->andReturn($filesResponse);

        $this->client->getCachedFiles('test-magnet');

        // Now add magnet - should clear cache and make new request
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'transfer/create', Mockery::any())
            ->andReturn($transferResponse);

        $result = $this->client->addMagnet('test-magnet');
        
        $this->assertEquals('transfer-123', $result);
    }


}
