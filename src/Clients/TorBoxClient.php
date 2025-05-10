<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
use RLeroi\Debrid\Exceptions\DebridException;

final class TorBoxClient implements ClientStrategy
{
    private const BASE_URL = 'https://api.torbox.app/v1/api/';

    public function __construct(?string $token, private ?ClientInterface $http = null)
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
        if (!$data || !$data['success']) {
            throw new DebridException($data['detail'] ?? 'unknown error');
        }

        return $data;
    }

    public function getCachedFiles(string $magnet): array
    {
        // todo getInfoHash helper
        //  regex: magnet:?xt=urn:btih:34FF1FAE9661D72152FB1FC31E27C15297072654&
        /*$infoHash = $this->getInfoHash($magnet);

        $response = $this->request(
            'GET',
            'torrents/checkcached',
            [
                'query' => [
                    'hash' => [$infoHash],
                    'format' => 'list',
                    'list_files' => 'true',
                ],
            ],
        );

        $returnHashes = [];
        foreach ($response['data'] ?? [] as $item) {
            $returnHashes[$item['hash']] = array_map(
                fn(array $file): array => [
                    'path' => $file['path'],
                    'size' => $file['size'],
                ],
                $item['files'] ?? [],
            );
        }

        return $returnHashes;*/

        return [];
    }

    /**
     * @return string Torrent ID
     * @throws DebridException|GuzzleException|JsonException
     */
    public function addMagnet(string $magnet): string
    {
        $response = $this->request(
            'POST',
            'torrents/createtorrent',
            [
                'form_params' => [
                    'magnet' => $magnet,
                ],
            ],
        );

        return (string)$response['data']['torrent_id'] ?? throw new DebridException('Magnet cannot be added');
    }

    public function isFileCached(string $magnet, string $path): bool
    {
        // TODO: Implement isFileCached() method.
    }

    public function getLink(string $magnet, string $path): string
    {
        // TODO: Implement getLink() method.
    }
}
