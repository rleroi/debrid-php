<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;

final class RealDebridClient implements ClientStrategy
{
    private const BASE_URL = 'https://api.real-debrid.com/rest/1.0/';

    public function __construct(private ?string $token, private ?ClientInterface $http = null)
    {
        $this->http ??= new Client();
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @throws GuzzleException|JsonException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        if (!$this->token) {
            throw new InvalidArgumentException('You must set the token before calling this method');
        }

        $mergedOptions = array_merge([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
            ]
        ], $options);

        $response = $this->http->request($method, $uri, $mergedOptions);

        $body = $response->getBody()->getContents();

        return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }

    public function getCachedFiles(string $magnet): array
    {
        // TODO: Implement getCachedFiles() method.
    }

    /*public function isFileCached(string $magnet, string $path): bool
    {
        $response = $this->getCachedFiles($magnet);

        return in_array(
            $path,
            array_map(
                fn(array $file): string => $file['path'],
                $response ?? [],
            ),
            true,
        );
    }*/

    public function isFileCached(string $magnet, string $path): bool
    {
        // TODO: Implement isFileCached() method.
    }

    public function getLink(string $magnet, string $path): string
    {
        // TODO: Implement getLink() method.
    }

    public function addMagnet(string $magnet): string
    {
        // TODO: Implement addMagnet() method.
    }
}
