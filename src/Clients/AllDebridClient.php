<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use RLeroi\Debrid\Exceptions\DebridException;
use InvalidArgumentException;

final class AllDebridClient implements ClientStrategy
{
    private const API_BASE_URL = 'https://api.alldebrid.com/v4';
    private const RATE_LIMIT_PER_SECOND = 12;
    private const RATE_LIMIT_PER_MINUTE = 600;

    private ?string $token = null;
    private ClientInterface $httpClient;

    public function __construct(?string $token = null, ?ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => self::API_BASE_URL,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Debrid-Library/1.0',
                'Accept' => 'application/json',
            ],
        ]);

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
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function getCachedFiles(string $magnet): array
    {
        // Step 1: Upload magnet (if not already uploaded)
        $magnetId = $this->addMagnet($magnet);
        
        // Step 2: Get magnet status and files
        $magnetInfo = $this->request('GET', 'magnet/status', [
            'query' => ['id' => $magnetId],
        ]);

        if (!isset($magnetInfo['data']['magnets'][0])) {
            throw new DebridException('Magnet not found or invalid response');
        }

        $magnetData = $magnetInfo['data']['magnets'][0];
        
        // Step 3: Check if magnet is ready
        $status = $magnetData['status'] ?? '';
        if ($status !== 'Ready') {
            throw new DebridException("Magnet is not ready. Current status: {$status}");
        }

        // Step 4: Get files and links
        $filesInfo = $this->request('GET', 'magnet/links', [
            'query' => ['id' => $magnetId],
        ]);

        if (!isset($filesInfo['data']['magnets'][0]['files'])) {
            return [];
        }

        // Step 5: Return the list of file paths
        $filePaths = [];
        foreach ($filesInfo['data']['magnets'][0]['files'] as $file) {
            if (isset($file['n'])) { // 'n' is the filename
                $filePaths[] = $file['n'];
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
        // Step 1: Upload magnet (if not already uploaded)
        $magnetId = $this->addMagnet($magnet);
        
        // Step 2: Get magnet status
        $magnetInfo = $this->request('GET', 'magnet/status', [
            'query' => ['id' => $magnetId],
        ]);

        if (!isset($magnetInfo['data']['magnets'][0])) {
            throw new DebridException('Magnet not found or invalid response');
        }

        $magnetData = $magnetInfo['data']['magnets'][0];
        
        // Step 3: Check if magnet is ready
        $status = $magnetData['status'] ?? '';
        if ($status !== 'Ready') {
            throw new DebridException("Magnet is not ready. Current status: {$status}");
        }

        // Step 4: Get files and links
        $filesInfo = $this->request('GET', 'magnet/links', [
            'query' => ['id' => $magnetId],
        ]);

        if (!isset($filesInfo['data']['magnets'][0]['files'])) {
            throw new DebridException('No files found in magnet');
        }

        // Step 5: Find the file that matches the requested path
        $targetFile = null;
        foreach ($filesInfo['data']['magnets'][0]['files'] as $file) {
            if (isset($file['n']) && $file['n'] === $path) {
                $targetFile = $file;
                break;
            }
        }

        if ($targetFile === null) {
            throw new DebridException("File with path '{$path}' not found in magnet");
        }

        // Step 6: Return the download link
        if (!isset($targetFile['link'])) {
            throw new DebridException('No download link available for the specified file');
        }

        return $targetFile['link'];
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function addMagnet(string $magnet): string
    {
        // Extract hash from magnet link for duplicate prevention
        $hash = $this->extractHashFromMagnet($magnet);
        
        // Check if magnet is already uploaded
        $existingMagnetId = $this->findExistingMagnetByHash($hash);
        if ($existingMagnetId !== null) {
            return $existingMagnetId;
        }

        // Upload new magnet
        $response = $this->request('POST', 'magnet/upload', [
            'form_params' => [
                'magnets[]' => $magnet,
            ],
        ]);

        if (!isset($response['data']['magnets'][0]['id'])) {
            throw new DebridException('Failed to upload magnet: No magnet ID returned');
        }

        return $response['data']['magnets'][0]['id'];
    }

    /**
     * Make HTTP request to AllDebrid API
     * 
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        if ($this->token === null) {
            throw new InvalidArgumentException('You must set the token before calling this method');
        }

        // Add authentication header
        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response = $this->httpClient->request($method, $uri, $options);
        $body = $response->getBody()->getContents();

        if (empty($body)) {
            if ($response->getStatusCode() >= 400) {
                throw new DebridException("HTTP {$response->getStatusCode()}: Empty response");
            }
            return [];
        }

        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        if (!isset($data['status'])) {
            throw new DebridException('Invalid response format: missing status field');
        }

        if ($data['status'] === 'error') {
            $errorCode = $data['error']['code'] ?? 'UNKNOWN';
            $errorMessage = $data['error']['message'] ?? 'Unknown error';
            throw new DebridException("API Error ({$errorCode}): {$errorMessage}");
        }

        return $data;
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
     * Find existing magnet by hash
     */
    private function findExistingMagnetByHash(string $hash): ?string
    {
        try {
            // Get all magnets and check for existing one with same hash
            $magnets = $this->request('GET', 'magnet/status');
            
            if (!isset($magnets['data']['magnets'])) {
                return null;
            }

            foreach ($magnets['data']['magnets'] as $magnet) {
                if (isset($magnet['hash']) && strtolower($magnet['hash']) === $hash) {
                    return $magnet['id'];
                }
            }
            
            return null;
        } catch (Exception $e) {
            // If we can't get the magnet list, assume it doesn't exist
            return null;
        }
    }
}
