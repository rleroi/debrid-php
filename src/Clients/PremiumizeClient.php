<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
use RLeroi\Debrid\Exceptions\DebridException;

final class PremiumizeClient implements ClientStrategy
{
    private const BASE_URL = 'https://www.premiumize.me/api/';

    private array $filesCache = [];

    public function __construct(private ?string $token, private ?ClientInterface $http = null)
    {
        $this->http ??= new Client();
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        if (!$this->token) {
            throw new InvalidArgumentException('You must set the token before calling this method');
        }

        $mergedOptions = array_merge([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'apikey' => $this->token,
            ],
        ], $options);

        $response = $this->http->request($method, $uri, $mergedOptions);

        $body = $response->getBody()->getContents();

        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        if (!$data || (isset($data['status']) && $data['status'] === 'error')) {
            $message = $data['message'] ?? 'Unknown API error';
            throw new DebridException($message);
        }

        return $data;
    }

    /**
     * @throws DebridException
     * @throws GuzzleException
     * @throws JsonException
     */
    private function isMagnetCached(string $magnet): bool
    {
        $response = $this->request(
            'GET',
            'cache/check',
            [
                'query' => [
                    'items[]' => $magnet,
                    'apikey' => $this->token,
                ],
            ],
        );

        // According to API docs, response is an array where each item is true if cached
        return isset($response['response'][0]) && $response['response'][0] === true;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    private function getFiles(string $magnet): array
    {
        if (!$this->isMagnetCached($magnet)) {
            return [];
        }

        if (isset($this->filesCache[$magnet])) {
            return $this->filesCache[$magnet];
        }

        // Use the correct endpoint for getting file list
        $response = $this->request('POST', 'transfer/directdl', [
            'form_params' => [
                'src' => $magnet,
            ],
        ]);

        $files = $response['content'] ?? [];
        $this->filesCache[$magnet] = $files;

        return $files;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function getCachedFiles(string $magnet): array
    {
        return array_map(fn(array $file): array => [
            'path' => $file['path'] ?? '',
            'size' => $file['size'] ?? 0,
        ], $this->getFiles($magnet));
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function isFileCached(string $magnet, string $path): bool
    {
        return (bool)$this->getLink($magnet, $path);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function getLink(string $magnet, string $path): string
    {
        foreach ($this->getFiles($magnet) as $file) {
            if (isset($file['path']) && $file['path'] === $path) {
                return $file['stream_link'] ?? $file['link'] ?? '';
            }
        }

        return '';
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function addMagnet(string $magnet): string
    {
        unset($this->filesCache[$magnet]);

        $response = $this->request('POST', 'transfer/create', [
            'form_params' => [
                'src' => $magnet,
            ],
        ]);

        if (!isset($response['id'])) {
            throw new DebridException('Failed to add magnet: No transfer ID returned');
        }

        return $response['id'];
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    // public function getTorrents(): array
    // {
    //     $response = $this->request('GET', 'transfer/list');
        
    //     return $response['transfers'] ?? [];
    // }
}
