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

        if (!$data || $data['status'] === 'error') {
            throw new DebridException($data['message']);
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
        return (bool)$this->request(
            'GET',
            'cache/check',
            [
                'query' => [
                    'items[]' => $magnet,
                    'apikey' => $this->token,
                ],
            ],
        )['response'][0];
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

        return $this->filesCache[$magnet] ?? $this->request(
            'POST',
            'transfer/directdl',
            [
                'form_params' => [
                    'src' => $magnet,
                ],
            ],
        )['content'] ?? [];
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws DebridException
     */
    public function getCachedFiles(string $magnet): array
    {
        return array_map(fn(array $file): array => [
            'path' => $file['path'],
            'size' => $file['size'],
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

        return $this->request('POST', 'transfer/create', [
            'form_params' => [
                'src' => $magnet,
            ],
        ])['id'] ?? throw new DebridException('Magnet cannot be added');
    }

    public function getTorrents(): array
    {
        //todo
        return [];
    }
}
