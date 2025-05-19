<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Tests\Unit\Clients;

use GuzzleHttp\Client;
use Mockery;
use PHPUnit\Framework\TestCase;
use RLeroi\Debrid\Clients\RealDebridClient;
use RLeroi\Debrid\Exceptions\DebridException;

final class RealDebridClientTest extends TestCase
{
    public function testItThrowsOnGetCachedFileNames(): void
    {
        $mock = Mockery::mock(Client::class);;
        $client = new RealDebridClient('test-token', $mock);
        $this->expectException(DebridException::class);
        $this->expectExceptionMessage('Real Debrid API doesn\'t support this method.');
        $client->getCachedFiles('test-hash');
    }
}
