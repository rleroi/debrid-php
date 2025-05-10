<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use JsonException;
use RLeroi\Debrid\Exceptions\DebridException;

final class PremiumizeClient implements ClientStrategy
{
    private const BASE_URL = 'https://www.premiumize.me/api/';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;

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
     * @throws GuzzleException|JsonException|DebridException
     */
    private function request(string $method, string $uri, array $options = [], int $attempt = 1): array
    {
        if (!$this->token) {
            throw new InvalidArgumentException('You must set the token before calling this method');
        }

        $mergedOptions = array_merge_recursive([
            'bases_uri' => self::BASE_URL,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'access_token' => $this->token,
            ],
        ], $options);

        try {
            $response = $this->http->request($method, $uri, $mergedOptions);
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

            if (!$data || (isset($data['status']) && $data['status'] === 'error')) {
                throw new DebridException($data['message'] ?? 'Unknown API error');
            }

            return $data;
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 429 && $attempt < self::MAX_RETRIES) {
                usleep(self::RETRY_DELAY_MS * 1000);
                return $this->request($method, $uri, $options, $attempt + 1);
            }
            throw new DebridException('HTTP request failed: ' . $e->getMessage());
        } catch (GuzzleException|JsonException $e) {
            throw new DebridException('Request error: ' . $e->getMessage());
        }
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    private function isMagnetCached(string $magnet): bool
    {
        $response = $this->request('POST', 'cache/check', [
            'form_params' => [
                'items[]' => $magnet,
            ],
        ]);

        return isset($response['response']) && is_array($response['response']) && $response['response'][0] === true;
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    private function getFiles(string $magnet): array
    {
        $cacheKey = md5($magnet); // Use hash to avoid special characters in cache key
        if (isset($this->filesCache[$cacheKey])) {
            return $this->filesCache[$cacheKey];
        }

        if (!$this->isMagnetCached($magnet)) {
            $this->addMagnet($magnet); // Add magnet if not cached
        }

        $response = $this->request('POST', 'transfer/directdl', [
            'form_params' => [
                'src' => $magnet,
            ],
        ]);

        $files = $response['content'] ?? [];
        $this->filesCache[$cacheKey] = $files;

        return $files;
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    public function getCachedFiles(string $magnet): array
    {
        if (!$this->isMagnetCached($magnet)) {
            return [];
        }

        $files = $this->getFiles($magnet);

        return array_map(fn(array $file): array => [
            'path' => $file['path'] ?? '',
            'size' => (int) ($file['size'] ?? 0),
        ], $files);
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    public function isFileCached(string $magnet, string $path): bool
    {
        if (!$this->isMagnetCached($magnet)) {
            return false;
        }

        $files = $this->getFiles($magnet);

        foreach ($files as $file) {
            if (isset($file['path']) && $file['path'] === $path) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    public function getLink(string $magnet, string $path): string
    {
        if (!$this->isMagnetCached($magnet)) {
            throw new DebridException('Magnet not cached: ' . $magnet);
        }

        $files = $this->getFiles($magnet);

        foreach ($files as $file) {
            if (isset($file['path']) && $file['path'] === $path) {
                return $file['link'] ?? throw new DebridException('No stream link available for file: ' . $path);
            }
        }

        throw new DebridException('File not found: ' . $path);
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    public function addMagnet(string $magnet): string
    {
        $cacheKey = md5($magnet);
        unset($this->filesCache[$cacheKey]);

        $response = $this->request('POST', 'transfer/create', [
            'form_params' => [
                'src' => $magnet,
            ],
        ]);

        return $response['id'] ?? throw new DebridException('Failed to add magnet: No transfer ID returned');
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    public function getTorrents(): array
    {
        $response = $this->request('GET', 'transfer/list');

        $torrents = $response['transfers'] ?? [];

        return array_map(fn(array $torrent): array => [
            'id' => $torrent['id'] ?? '',
            'name' => $torrent['name'] ?? '',
            'status' => $torrent['status'] ?? '',
            'progress' => (float) ($torrent['progress'] ?? 0),
            'size' => (int) ($torrent['size'] ?? 0),
        ], $torrents);
    }
}
