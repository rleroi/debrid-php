<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use RLeroi\Debrid\DTOs\DebridFile;
use RLeroi\Debrid\Exceptions\DebridException;
use RLeroi\Debrid\Mappers\PremiumizeMapper;
use RuntimeException;

final class PremiumizeClient implements ClientStrategy
{
    private const BASE_URL = 'https://www.premiumize.me/api/';

    private PremiumizeMapper $mapper;

    public function __construct(private ?string $token, private ?ClientInterface $http = null)
    {
        $this->http ??= new Client();
        $this->mapper = new PremiumizeMapper();
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
            throw new RuntimeException('You must set the token before calling this method');
        }

        $mergedOptions = array_merge_recursive([
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
     * @return DebridFile[]
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function getCachedFiles(string $magnet): array
    {
        $response = $this->request('POST', 'transfer/directdl', [
            'form_params' => [
                'src' => $magnet,
            ],
        ]);

        return $this->mapper->mapFiles($response);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function isFileCached(string $magnet, string $path): bool
    {
        $files = $this->getCachedFiles($magnet);
        foreach ($files as $file) {
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
        $files = $this->getCachedFiles($magnet);
        foreach ($files as $file) {
            if ($file->path === $path) {
                return $file->data['stream_link'] ?? $file->data['link'];
            }
        }

        throw new DebridException('File not cached');
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function addMagnet(string $magnet): string
    {
        $response = $this->request('POST', 'transfer/create', [
            'form_params' => [
                'src' => $magnet,
            ],
        ]);

        if (!isset($response['id'])) {
            throw new DebridException('Failed to add magnet');
        }

        return (string)$response['id'];
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    private function isMagnetCached(string $magnet): bool
    {
        $response = $this->request(
            'GET',
            'cache/check',
            [
                'query' => [
                    'items[]' => $magnet,
                ],
            ],
        );

        return isset($response['response']) && $response['response'][0];
    }

}
