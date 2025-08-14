<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
use RLeroi\Debrid\Exceptions\DebridException;

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
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
            ]
        ], $options);

        $response = $this->http->request($method, $uri, $mergedOptions);

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

        // Check for API errors
        if (isset($data['error'])) {
            throw new DebridException($data['error']);
        }

        return $data;
    }



    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function getCachedFiles(string $magnet): array
    {
        // Step 1: Add magnet (if not already added)
        $torrentId = $this->addMagnet($magnet);
        
        // Step 2: Get torrent info to get the list of files
        $torrentInfo = $this->request('GET', "torrents/info/{$torrentId}");
        $files = $torrentInfo['files'] ?? [];
        
        // Step 3: Check if torrent is ready (downloaded or waiting for file selection)
        $status = $torrentInfo['status'] ?? '';
        if ($status !== 'downloaded' && $status !== 'waiting_files_selection') {
            throw new DebridException("Torrent is not ready. Current status: {$status}");
        }
        
        // Step 4: Return the list of file paths
        $filePaths = [];
        foreach ($files as $file) {
            if (isset($file['path'])) {
                $filePaths[] = $file['path'];
            }
        }
        
        return $filePaths;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function isFileCached(string $magnet, string $path): bool
    {
        // Get all cached files and check if the specific path exists
        $cachedFiles = $this->getCachedFiles($magnet);
        return in_array($path, $cachedFiles, true);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function getLink(string $magnet, string $path): string
    {
        // Step 1: Add magnet
        $torrentId = $this->addMagnet($magnet);
        
        // Step 2: Get torrent info to map paths to file IDs
        $torrentInfo = $this->request('GET', "torrents/info/{$torrentId}");
        $files = $torrentInfo['files'] ?? [];
        
        // Step 3: Check if torrent is ready (downloaded or waiting for file selection)
        $status = $torrentInfo['status'] ?? '';
        if ($status !== 'downloaded' && $status !== 'waiting_files_selection') {
            throw new DebridException("Torrent is not ready. Current status: {$status}");
        }
        
        // Step 4: Find the file ID that matches the requested path
        $targetFileId = null;
        foreach ($files as $file) {
            if (isset($file['path']) && $file['path'] === $path) {
                $targetFileId = $file['id'];
                break;
            }
        }
        
        if ($targetFileId === null) {
            throw new DebridException("File with path '{$path}' not found in torrent");
        }
        
        // Step 5: Select only the specific file
        $this->request('POST', "torrents/selectFiles/{$torrentId}", [
            'form_params' => [
                'files' => (string) $targetFileId,
            ],
        ]);
        
        // Step 6: Get updated torrent info to get the download links for selected files
        $updatedTorrentInfo = $this->request('GET', "torrents/info/{$torrentId}");
        $links = $updatedTorrentInfo['links'] ?? [];
        
        if (empty($links)) {
            throw new DebridException('No download links available for this torrent');
        }
        
        // Step 7: Get the link for the selected file
        // Since we only selected one file, it should be the first (and only) link
        if (count($links) !== 1) {
            throw new DebridException('No download link found for the specified file');
        }
        
        $restrictedLink = $links[0];
        
        // Step 8: Unrestrict the link to get the actual download URL
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
