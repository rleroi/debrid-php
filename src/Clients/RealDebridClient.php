<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use RLeroi\Debrid\Dtos\DebridFileDto;
use RLeroi\Debrid\Exceptions\DebridException;
use RLeroi\Debrid\Mappers\RealDebridMapper;
use RuntimeException;

final class RealDebridClient implements ClientStrategy
{
    private const BASE_URL = 'https://api.real-debrid.com/rest/1.0/';
    private RealDebridMapper $mapper;

    public function __construct(private ?string $token, private ?ClientInterface $http = null)
    {
        $this->http ??= new Client();
        $this->mapper = new RealDebridMapper();
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
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
            'base_uri' => self::BASE_URL,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
            ]
        ], $options);

        try {
            $response = $this->http->request($method, $uri, $mergedOptions);
        } catch(RequestException $e) {
            throw new DebridException($e->getMessage());
        }
        $body = $response->getBody()->getContents();

        // Handle empty responses (like selectFiles endpoint)
        if (empty($body)) {
            // Check for HTTP errors even with empty response
            if ($response->getStatusCode() >= 400) {
                throw new DebridException("HTTP {$response->getStatusCode()}: Empty response");
            }
            return [];
        }

        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        if (isset($data['error'])) {
            $error = $data['error'];

            // Throw specific exceptions based on error message
            if (strpos($error, 'Bad token') !== false || strpos($error, 'Unauthorized') !== false) {
                throw new DebridException('Authentication failed: ' . $error);
            }

            throw new DebridException($error);
        }

        return $data;
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

        // Try to find the torrent in the user's torrent list
        $response = $this->request('GET', 'torrents');
        foreach ($response as $torrent) {
            if (strtolower($torrent['hash'] ?? '') === strtolower($hash)) {
                // Get torrent info to get the list of files
                $torrentInfo = $this->request('GET', "torrents/info/{$torrent['id']}");

                if ($torrentInfo['status'] ?? '' === 'downloaded') {
                    return $this->mapper->mapFiles($torrentInfo);
                }
            }
        }

        // Torrent not added or downloaded
        return [];
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function isFileCached(string $magnet, string $path): bool
    {
        $normalizedPath = ltrim($path, '/');
        $cachedFiles = $this->getCachedFiles($magnet);
        foreach ($cachedFiles as $file) {
            if (ltrim($file->path, '/') === $normalizedPath) {
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
        // Real-Debrid: Find torrent and handle file selection
        $hash = $this->extractHashFromMagnet($magnet);

        // Try to find the torrent in the user's torrent list
        $response = $this->request('GET', 'torrents');
        $torrentId = null;

        foreach ($response as $torrent) {
            if (strtolower($torrent['hash'] ?? '') === strtolower($hash)) {
                $torrentId = $torrent['id'];
                break;
            }
        }

        if ($torrentId === null) {
            throw new DebridException("Torrent is not added. Please add it first using addMagnet().");
        }

        $torrentInfo = $this->request('GET', "torrents/info/{$torrentId}");
        $files = $torrentInfo['files'] ?? [];
        $links = $torrentInfo['links'] ?? [];

        if (empty($links)) {
            throw new DebridException('No download links available for this torrent');
        }

        $status = $torrentInfo['status'] ?? '';
        if ($status !== 'downloaded') {
            throw new DebridException("Torrent is not ready. Current status: {$status}");
        }

        // Find the file ID that matches the requested path
        $targetFileIndex = null;
        foreach ($files as $file) {
            $filePath = isset($file['path']) ? ltrim($file['path'], '/') : '';
            if ($filePath === ltrim($path, '/')) {
                $targetFileIndex = $file['id'] - 1;
                break;
            }
        }

        if ($targetFileIndex === null) {
            throw new DebridException("File with path '{$path}' not found in torrent");
        }

        $restrictedLink = $links[$targetFileIndex] ?? null;
        if ($restrictedLink === null) {
            throw new DebridException("File with path '{$path}' not found in torrent");
        }

        // Unrestrict the link to get the actual download URL
        $unrestrictedResponse = $this->request('POST', 'unrestrict/link', [
            'form_params' => [
                'link' => $restrictedLink,
            ],
        ]);

        if (!isset($unrestrictedResponse['download'])) {
            throw new DebridException('Failed to unrestrict download link');
        }

        return $unrestrictedResponse['download'];
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function addMagnet(string $magnet): string
    {
        // Extract hash from magnet link
        $hash = $this->extractHashFromMagnet($magnet);

        // Check if torrent is already added
        $existingTorrentId = $this->findExistingTorrentByHash($hash);
        if ($existingTorrentId !== null) {
            return $existingTorrentId;
        }

        // Add new magnet
        $response = $this->request('POST', 'torrents/addMagnet', [
            'form_params' => [
                'magnet' => $magnet,
            ],
        ]);

        if (!isset($response['id'])) {
            throw new DebridException('Failed to add magnet: No torrent ID returned');
        }

        $this->request('POST', 'torrents/selectFiles/' . $response['id'], [
            'form_params' => [
                'files' => 'all',
            ],
        ]);

        return $response['id'];
    }

    /**
     * Extract hash from magnet link
     */
    private function extractHashFromMagnet(string $magnet): string
    {
        if (preg_match('/urn:btih:([a-fA-F0-9]{40})/', $magnet, $matches)) {
            return strtolower($matches[1]);
        }

        throw new DebridException('Invalid magnet link: Could not extract hash');
    }

    /**
     * Find existing torrent by hash
     */
    private function findExistingTorrentByHash(string $hash): ?string
    {
        try {
            $torrents = $this->request('GET', 'torrents');
            foreach ($torrents as $torrent) {
                if (isset($torrent['hash']) && strtolower($torrent['hash']) === $hash) {
                    return $torrent['id'];
                }
            }

            return null;
        } catch (Exception $e) {
            // If we can't get the torrent list, assume it doesn't exist
            return null;
        }
    }
}
