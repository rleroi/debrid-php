<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
use RLeroi\Debrid\DTOs\DebridFile;
use RLeroi\Debrid\Exceptions\DebridException;
use RLeroi\Debrid\Mappers\AllDebridMapper;
use RuntimeException;

final class AllDebridClient implements ClientStrategy
{
    private const API_BASE_URL = 'https://api.alldebrid.com';

    private ?string $token = null;
    private ClientInterface $httpClient;
    private AllDebridMapper $mapper;

    public function __construct(?string $token = null, ?ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => self::API_BASE_URL,
            'timeout'  => 30,
            'headers'  => [
                'Accept' => 'application/json',
            ],
        ]);

        $this->mapper = new AllDebridMapper();

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
     *
     * @return DebridFile[]
     */
    public function getCachedFiles(string $magnet): array
    {
        $hash = $this->extractHashFromMagnet($magnet);
        $existingMagnetId = $this->findExistingMagnetByHash($hash);

        if ($existingMagnetId === null) {
            return [];
        }
        $response = $this->request('GET', '/v4.1/magnet/status', [
            'query' => ['id' => $existingMagnetId],
        ]);

        if (!isset($response['data']['magnets']['files'])) {
            return [];
        }

        return $this->mapper->mapFiles($response);
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
        foreach ($cachedFiles as $file) {
            if ($file->path === ltrim($path, '/')) {
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
        $files = $this->getCachedFiles($magnet);
        // Find the file that matches the requested path
        foreach ($files as $file) {
            if ($file->path === $path) {
                if ($file->data['l'] ?? null) {
                    return $this->unrestrictLink($file->data['l']);
                }

                throw new DebridException('No download link available for the specified file');
            }
        }

        throw new DebridException("File with path '{$path}' not found in magnet");
    }

    /**
     * Unrestrict a AllDebrid link to get the final download URL.
     *
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    private function unrestrictLink(string $link): string
    {
        $response = $this->request('POST', '/v4/link/unlock', [
            'form_params' => [
                'link' => $link,
            ],
        ]);

        if (!isset($response['data']['link'])) {
            throw new DebridException('Failed to unrestrict AllDebrid link');
        }

        return $response['data']['link'];
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

        // Upload new magnet using form-encoded data (not JSON)
        $response = $this->request('POST', '/v4/magnet/upload', [
            'form_params' => [
                'magnets[]' => $magnet,
            ],
        ]);

        if (!isset($response['data']['magnets'][0]['id'])) {
            throw new DebridException('Failed to upload magnet: No magnet ID returned');
        }

        return (string) $response['data']['magnets'][0]['id'];
    }

    /**
     * Make HTTP request to AllDebrid API.
     *
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        if ($this->token === null) {
            throw new RuntimeException('You must set the token before calling this method');
        }

        // Add authentication as query parameter (AllDebrid uses apikey, not Bearer token)
        $options['query'] = array_merge($options['query'] ?? [], [
            'agent'  => 'debridlib',
            'apikey' => $this->token,
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

            // Throw specific exceptions based on error code
            switch ($errorCode) {
                case 'AUTH_BAD_APIKEY':
                case 'AUTH_MISSING_APIKEY':
                case 'AUTH_USER_BANNED':
                    throw new DebridException("Authentication failed ({$errorCode}): {$errorMessage}");
                case 'LINK_PASS_PROTECTED':
                case 'LINK_NOT_SUPPORTED':
                case 'LINK_HOST_NOT_SUPPORTED':
                    throw new DebridException("Link not supported ({$errorCode}): {$errorMessage}");
                case 'MAGNET_INVALID':
                case 'MAGNET_TOO_MANY_FILES':
                    throw new DebridException("Invalid magnet ({$errorCode}): {$errorMessage}");
                case 'MAGNET_NO_SERVER':
                case 'MAGNET_PROCESSING':
                    throw new DebridException("Torrent not ready ({$errorCode}): {$errorMessage}");
                case 'USER_LINK_INVALID':
                case 'LINK_ERROR':
                    throw new DebridException("File error ({$errorCode}): {$errorMessage}");
                default:
                    throw new DebridException("API Error ({$errorCode}): {$errorMessage}");
            }
        }

        return $data;
    }

    /**
     * Extract hash from magnet link.
     *
     * @throws DebridException
     */
    private function extractHashFromMagnet(string $magnet): string
    {
        if (preg_match('/urn:btih:([a-fA-F0-9]{40})/', $magnet, $matches)) {
            return strtolower($matches[1]);
        }

        throw new DebridException('Invalid magnet link: Could not extract hash');
    }

    /**
     * Find existing magnet by hash.
     */
    private function findExistingMagnetByHash(string $hash): ?string
    {
        // Get all magnets and check for existing one with same hash
        $magnets = $this->request('GET', '/v4.1/magnet/status');

        if (!isset($magnets['data']['magnets'])) {
            return null;
        }

        foreach ($magnets['data']['magnets'] as $magnet) {
            if (isset($magnet['hash']) && strtolower($magnet['hash']) === $hash) {
                return (string) $magnet['id'];
            }
        }

        return null;
    }
}
