<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use JsonException;
use RLeroi\Debrid\Dtos\DebridFileDto;
use RLeroi\Debrid\Exceptions\DebridException;
use RLeroi\Debrid\Mappers\TorBoxMapper;
use RuntimeException;

final class TorBoxClient implements ClientStrategy
{
    private const API_BASE_URL = 'https://api.torbox.app';

    private ?string $token = null;
    private ClientInterface $httpClient;
    private TorBoxMapper $mapper;

    public function __construct(?string $token = null, ?ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => self::API_BASE_URL,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $this->mapper = new TorBoxMapper();

        if ($token !== null) {
            $this->setToken($token);
        }
    }

    public function setToken(string $token): void
    {
        if (empty($token)) {
            throw new InvalidArgumentException('Token cannot be empty');
        }

        $this->token = $token;
    }

    /**
     * @return DebridFileDto[]
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function getCachedFiles(string $magnet): array
    {
        $hash = $this->extractHashFromMagnet($magnet);
        $response = $this->request(
            'GET',
            '/v1/api/torrents/checkcached',
            [
                'query' => [
                    'hash' => $hash,
                    'format' => 'list',
                    'list_files' => true,
                ]
            ],
        );

        return $this->mapper->mapFiles($response);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function isFileCached(string $magnet, string $path): bool
    {
        $path = trim(preg_replace('/  +/', ' ', ltrim($path, '/')));

        // Get all cached files and check if the specific path exists
        $cachedFiles = $this->getCachedFiles($magnet);
        foreach ($cachedFiles as $file) {
            if ($file->path === $path) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function getLink(string $magnet, string $path): string
    {
        $path = trim(preg_replace('/  +/', ' ', ltrim($path, '/')));

        // create torrent if cached (will only add once)
        $response = $this->request('POST', '/v1/api/torrents/createtorrent', [
            'form_params' => [
                'magnet' => $magnet,
                'allow_zip' => false,
                'add_only_if_cached' => true, // false when calling addMagnet()
            ],
        ]);

        $torrentId = $response['data']['torrent_id'] ?? null;
        if (!$torrentId) {
            throw new DebridException('Failed to create torrent, maybe torrent is not cached?');
        }

        // get file id from the torrent info
        $response = $this->request(
            'GET',
            '/v1/api/torrents/mylist',
            [
                'bypass_cache' => true,
                'id' => $torrentId,
                'limit' => 1,
            ],
        );

        $files = $this->mapper->mapFiles($response);
        $fileId = null;
        // Find the file that matches the requested path
        foreach ($files as $file) {
            if ($file->path === $path) {
                $fileId = $file->data['id'] ?? 0;
                break;
            }
        }

        if (is_null($fileId)) {
            throw new DebridException("File with path '{$path}' not found in magnet");
        }

        // get download link from $torrentId and $fileId
        $response = $this->request(
            'GET',
            '/v1/api/torrents/requestdl',
            [
                'query' => [
                    'token' => $this->token, // required for this endpoint
                    'torrent_id' => $torrentId,
                    'file_id' => $fileId,
                    'zip_link' => false,
                    'redirect' => false,
                ],
            ]
        );

        return $response['data'] ?? '';
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function addMagnet(string $magnet): string
    {
        // create torrent (will only add once)
        $response = $this->request('POST', '/v1/api/torrents/createtorrent', [
            'form_params' => [
                'magnet' => $magnet,
                'allow_zip' => false,
                'add_only_if_cached' => false,
            ],
        ]);

        return $response['data']['torrent_id'] ?? '';
    }

    /**
     * @throws GuzzleException|JsonException
     * @throws DebridException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        if (!$this->token) {
            throw new RuntimeException('You must set the token before calling this method');
        }

        $mergedOptions = array_merge([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token, // required for all other endpoints
                'Accept' => 'application/json',
            ]
        ], $options);

        try {
            $response = $this->httpClient->request($method, $uri, $mergedOptions);
        } catch(RequestException $e) {
            throw new DebridException($e->getMessage());
        }

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        if (!isset($data['success']) || $data['success'] === false) {
            $error = $data['detail'];

            throw new DebridException($error);
        }

        return $data;
    }

    /**
     * Extract hash from magnet link
     * @throws DebridException
     */
    private function extractHashFromMagnet(string $magnet): string
    {
        if (preg_match('/urn:btih:([a-fA-F0-9]{40})/', $magnet, $matches)) {
            return strtolower($matches[1]);
        }

        throw new DebridException('Invalid magnet link: Could not extract hash');
    }
}
