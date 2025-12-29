<?php

declare(strict_types=1);

namespace Tests\Feature;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RLeroi\Debrid\Client;
use RLeroi\Debrid\Clients\AllDebridClient;
use RLeroi\Debrid\Clients\ClientStrategy;
use RLeroi\Debrid\Clients\PremiumizeClient;
use RLeroi\Debrid\Clients\RealDebridClient;
use RLeroi\Debrid\Dtos\DebridFileDto;
use RLeroi\Debrid\Exceptions\DebridException;

final class ClientTest extends TestCase
{
    private Client $client;
    private string $testToken;
    private string $testMagnet;
    private string $testPath;

    protected function setUp(): void
    {
        $this->client = new Client();
        $this->testToken = 'test_token_123';
        $this->testMagnet = 'magnet:?xt=urn:btih:test_hash_123';
        $this->testPath = 'test/file.mp4';
    }

    public function testSetClientRealDebrid(): void
    {
        $result = $this->client->setClientRealDebrid($this->testToken);

        $this->assertSame($this->client, $result);
        $this->assertInstanceOf(RealDebridClient::class, $this->getClientProperty($this->client, 'client'));
    }

    public function testSetClientRealDebridWithoutToken(): void
    {
        $this->client->setToken($this->testToken);
        $result = $this->client->setClientRealDebrid();

        $this->assertSame($this->client, $result);
        $this->assertInstanceOf(RealDebridClient::class, $this->getClientProperty($this->client, 'client'));
    }

    public function testSetClientPremiumize(): void
    {
        $result = $this->client->setClientPremiumize($this->testToken);

        $this->assertSame($this->client, $result);
        $this->assertInstanceOf(PremiumizeClient::class, $this->getClientProperty($this->client, 'client'));
    }

    public function testSetClientPremiumizeWithoutToken(): void
    {
        $this->client->setToken($this->testToken);
        $result = $this->client->setClientPremiumize();

        $this->assertSame($this->client, $result);
        $this->assertInstanceOf(PremiumizeClient::class, $this->getClientProperty($this->client, 'client'));
    }

    public function testSetClientAllDebrid(): void
    {
        $result = $this->client->setClientAllDebrid($this->testToken);

        $this->assertSame($this->client, $result);
        $this->assertInstanceOf(AllDebridClient::class, $this->getClientProperty($this->client, 'client'));
    }

    public function testSetClientAllDebridWithoutToken(): void
    {
        $this->client->setToken($this->testToken);
        $result = $this->client->setClientAllDebrid();

        $this->assertSame($this->client, $result);
        $this->assertInstanceOf(AllDebridClient::class, $this->getClientProperty($this->client, 'client'));
    }

    public function testSetToken(): void
    {
        $result = $this->client->setToken($this->testToken);

        $this->assertSame($this->client, $result);
        $this->assertEquals($this->testToken, $this->getClientProperty($this->client, 'token'));
    }

    public function testSetTokenUpdatesExistingClient(): void
    {
        $this->client->setClientRealDebrid('old_token');
        $result = $this->client->setToken($this->testToken);

        $this->assertSame($this->client, $result);
        $this->assertEquals($this->testToken, $this->getClientProperty($this->client, 'token'));
    }

    public function testGetCachedFilesThrowsExceptionWhenNoClient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No client provided');

        $this->client->getCachedFiles($this->testMagnet);
    }

    public function testGetCachedFilesThrowsExceptionWhenNoToken(): void
    {
        $this->client->setClientRealDebrid();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No token provided');

        $this->client->getCachedFiles($this->testMagnet);
    }

    public function testGetCachedFilesWithRealDebrid(): void
    {
        $this->client->setClientRealDebrid($this->testToken);

        // Mock the client to return test data
        $mockClient = $this->createMock(ClientStrategy::class);
        $expectedFiles = [
            new DebridFileDto('test/file1.mp4', 1024, ['id' => '1']),
            new DebridFileDto('test/file2.mp4', 2048, ['id' => '2']),
        ];

        $mockClient->expects($this->once())
            ->method('getCachedFiles')
            ->with($this->testMagnet)
            ->willReturn($expectedFiles);

        $this->setClientProperty($this->client, 'client', $mockClient);

        $result = $this->client->getCachedFiles($this->testMagnet);

        $this->assertSame($expectedFiles, $result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(DebridFileDto::class, $result[0]);
    }

    public function testIsFileCachedThrowsExceptionWhenNoClient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No client provided');

        $this->client->isFileCached($this->testMagnet, $this->testPath);
    }

    public function testIsFileCachedThrowsExceptionWhenNoToken(): void
    {
        $this->client->setClientRealDebrid();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No token provided');

        $this->client->isFileCached($this->testMagnet, $this->testPath);
    }

    public function testIsFileCachedWithRealDebrid(): void
    {
        $this->client->setClientRealDebrid($this->testToken);

        $mockClient = $this->createMock(ClientStrategy::class);
        $mockClient->expects($this->once())
            ->method('isFileCached')
            ->with($this->testMagnet, $this->testPath)
            ->willReturn(true);

        $this->setClientProperty($this->client, 'client', $mockClient);

        $result = $this->client->isFileCached($this->testMagnet, $this->testPath);

        $this->assertTrue($result);
    }

    public function testIsFileCachedReturnsFalse(): void
    {
        $this->client->setClientRealDebrid($this->testToken);

        $mockClient = $this->createMock(ClientStrategy::class);
        $mockClient->expects($this->once())
            ->method('isFileCached')
            ->with($this->testMagnet, $this->testPath)
            ->willReturn(false);

        $this->setClientProperty($this->client, 'client', $mockClient);

        $result = $this->client->isFileCached($this->testMagnet, $this->testPath);

        $this->assertFalse($result);
    }

    public function testAddMagnetThrowsExceptionWhenNoClient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No client provided');

        $this->client->addMagnet($this->testMagnet);
    }

    public function testAddMagnetThrowsExceptionWhenNoToken(): void
    {
        $this->client->setClientRealDebrid();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No token provided');

        $this->client->addMagnet($this->testMagnet);
    }

    public function testAddMagnetWithRealDebrid(): void
    {
        $this->client->setClientRealDebrid($this->testToken);

        $mockClient = $this->createMock(ClientStrategy::class);
        $expectedTorrentId = 'torrent_123';
        
        $mockClient->expects($this->once())
            ->method('addMagnet')
            ->with($this->testMagnet)
            ->willReturn($expectedTorrentId);

        $this->setClientProperty($this->client, 'client', $mockClient);

        $result = $this->client->addMagnet($this->testMagnet);

        $this->assertEquals($expectedTorrentId, $result);
    }

    public function testGetLinkThrowsExceptionWhenNoClient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No client provided');

        $this->client->getLink($this->testMagnet, $this->testPath);
    }

    public function testGetLinkThrowsExceptionWhenNoToken(): void
    {
        $this->client->setClientRealDebrid();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No token provided');

        $this->client->getLink($this->testMagnet, $this->testPath);
    }

    public function testGetLinkWithRealDebrid(): void
    {
        $this->client->setClientRealDebrid($this->testToken);

        $mockClient = $this->createMock(ClientStrategy::class);
        $expectedLink = 'https://example.com/download/test_file.mp4';
        
        $mockClient->expects($this->once())
            ->method('getLink')
            ->with($this->testMagnet, $this->testPath)
            ->willReturn($expectedLink);

        $this->setClientProperty($this->client, 'client', $mockClient);

        $result = $this->client->getLink($this->testMagnet, $this->testPath);

        $this->assertEquals($expectedLink, $result);
    }

    public function testClientMethodsWithPremiumize(): void
    {
        $this->client->setClientPremiumize($this->testToken);

        $mockClient = $this->createMock(ClientStrategy::class);
        $expectedFiles = [new DebridFileDto('test/file.mp4', 1024, ['id' => '1'])];
        $expectedLink = 'https://premiumize.me/download/test_file.mp4';
        $expectedTorrentId = 'premiumize_torrent_123';

        $mockClient->expects($this->once())
            ->method('getCachedFiles')
            ->with($this->testMagnet)
            ->willReturn($expectedFiles);

        $mockClient->expects($this->once())
            ->method('isFileCached')
            ->with($this->testMagnet, $this->testPath)
            ->willReturn(true);

        $mockClient->expects($this->once())
            ->method('addMagnet')
            ->with($this->testMagnet)
            ->willReturn($expectedTorrentId);

        $mockClient->expects($this->once())
            ->method('getLink')
            ->with($this->testMagnet, $this->testPath)
            ->willReturn($expectedLink);

        $this->setClientProperty($this->client, 'client', $mockClient);

        $this->assertSame($expectedFiles, $this->client->getCachedFiles($this->testMagnet));
        $this->assertTrue($this->client->isFileCached($this->testMagnet, $this->testPath));
        $this->assertEquals($expectedTorrentId, $this->client->addMagnet($this->testMagnet));
        $this->assertEquals($expectedLink, $this->client->getLink($this->testMagnet, $this->testPath));
    }

    public function testClientMethodsWithAllDebrid(): void
    {
        $this->client->setClientAllDebrid($this->testToken);

        $mockClient = $this->createMock(ClientStrategy::class);
        $expectedFiles = [new DebridFileDto('test/file.mp4', 1024, ['id' => '1'])];
        $expectedLink = 'https://alldebrid.com/download/test_file.mp4';
        $expectedTorrentId = 'alldebrid_torrent_123';

        $mockClient->expects($this->once())
            ->method('getCachedFiles')
            ->with($this->testMagnet)
            ->willReturn($expectedFiles);

        $mockClient->expects($this->once())
            ->method('isFileCached')
            ->with($this->testMagnet, $this->testPath)
            ->willReturn(true);

        $mockClient->expects($this->once())
            ->method('addMagnet')
            ->with($this->testMagnet)
            ->willReturn($expectedTorrentId);

        $mockClient->expects($this->once())
            ->method('getLink')
            ->with($this->testMagnet, $this->testPath)
            ->willReturn($expectedLink);

        $this->setClientProperty($this->client, 'client', $mockClient);

        $this->assertSame($expectedFiles, $this->client->getCachedFiles($this->testMagnet));
        $this->assertTrue($this->client->isFileCached($this->testMagnet, $this->testPath));
        $this->assertEquals($expectedTorrentId, $this->client->addMagnet($this->testMagnet));
        $this->assertEquals($expectedLink, $this->client->getLink($this->testMagnet, $this->testPath));
    }

    public function testMethodChaining(): void
    {
        $result = $this->client
            ->setToken($this->testToken)
            ->setClientRealDebrid()
            ->setToken('new_token');

        $this->assertSame($this->client, $result);
        $this->assertEquals('new_token', $this->getClientProperty($this->client, 'token'));
    }

    public function testDebridExceptionPropagation(): void
    {
        $this->client->setClientRealDebrid($this->testToken);

        $mockClient = $this->createMock(ClientStrategy::class);
        $mockClient->expects($this->once())
            ->method('getCachedFiles')
            ->with($this->testMagnet)
            ->willThrowException(new DebridException('API Error'));

        $this->setClientProperty($this->client, 'client', $mockClient);

        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('API Error');

        $this->client->getCachedFiles($this->testMagnet);
    }

    /**
     * Helper method to access private properties for testing
     */
    private function getClientProperty(Client $client, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($client);
    }

    /**
     * Helper method to set private properties for testing
     */
    private function setClientProperty(Client $client, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($client, $value);
    }
}
