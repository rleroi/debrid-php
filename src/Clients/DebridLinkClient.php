<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
use RLeroi\Debrid\Exceptions\DebridException;

final class DebridLinkClient implements ClientStrategy
{
    private const BASE_URL = 'https://api.debrid-link.com/v2/';

    public function __construct(private ?string $token, private ?ClientInterface $http = null)
    {
        $this->http ??= new Client();
    }

    public function setToken(string $token): void
    {
        if (empty($token)) {
            throw new InvalidArgumentException('Token cannot be empty');
        }
        $this->token = $token;
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
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
            ],
        ], $options);

        $response = $this->http->request($method, $uri, $mergedOptions);

        $body = $response->getBody()->getContents();

        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        
        // Check for API errors
        if (isset($data['error'])) {
            throw new DebridException($data['error']);
        }

        return $data;
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    public function getCachedFiles(string $magnet): array
    {
        // Step 1: Extract hash from magnet link
        $infoHash = $this->extractHashFromMagnet($magnet);

        // Step 2: Check if torrent is already cached
        $cachedInfo = $this->checkCachedTorrent($infoHash);
        
        if (empty($cachedInfo)) {
            // Torrent is not cached, return empty array
            return [];
        }

        // Step 3: Return file paths from cached info
        $filePaths = [];
        foreach ($cachedInfo as $file) {
            if (isset($file['path'])) {
                $filePaths[] = $file['path'];
            }
        }
        
        return $filePaths;
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    public function isFileCached(string $magnet, string $path): bool
    {
        // Get all cached files and check if the specific path exists
        $cachedFiles = $this->getCachedFiles($magnet);
        return in_array($path, $cachedFiles, true);
    }

    /**
     * @throws GuzzleException|JsonException|DebridException
     */
    public function getLink(string $magnet, string $path): string
    {
        // Step 1: Extract hash from magnet link
        $infoHash = $this->extractHashFromMagnet($magnet);

        // Step 2: Check if torrent is already cached
        $cachedInfo = $this->checkCachedTorrent($infoHash);
        
        if (empty($cachedInfo)) {
            throw new DebridException("Torrent is not cached. Please add it first using addMagnet().");
        }

        // Step 3: Find the target file in cached info
        $targetFile = null;
        foreach ($cachedInfo as $file) {
            if (isset($file['path']) && $file['path'] === $path) {
                $targetFile = $file;
                break;
            }
        }
        
        if (!$targetFile) {
            throw new DebridException("File not found: {$path}");
        }
        
        // Step 4: Get download link for the cached file
        return $this->getFileDownloadLink($infoHash, $targetFile['id'] ?? $path);
    }

    /**
     * @return string Torrent ID
     * @throws DebridException|GuzzleException|JsonException
     */
    public function addMagnet(string $magnet): string
    {
        $response = $this->request(
            'POST',
            'torrents/add',
            [
                'form_params' => [
                    'magnet' => $magnet,
                ],
            ],
        );

        return (string)($response['data']['torrent_id'] ?? throw new DebridException('Magnet cannot be added'));
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
     * Check if torrent is cached
     */
    private function checkCachedTorrent(string $infoHash): array
    {
        try {
            $response = $this->request(
                'GET',
                'torrents/check',
                [
                    'query' => [
                        'hash' => $infoHash,
                    ],
                ],
            );

            if (isset($response['data']['files'])) {
                return $response['data']['files'];
            }

            return [];
        } catch (DebridException $e) {
            // If torrent is not cached, return empty array
            return [];
        }
    }

    /**
     * Get download link for a specific file
     */
    private function getFileDownloadLink(string $torrentId, string $fileId): string
    {
        $response = $this->request(
            'POST',
            'torrents/download',
            [
                'form_params' => [
                    'torrent_id' => $torrentId,
                    'file_id' => $fileId,
                ],
            ],
        );

        return $response['data']['download_link'] ?? throw new DebridException('Failed to get download link');
    }
}
