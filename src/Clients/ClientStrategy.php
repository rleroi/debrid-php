<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\ClientInterface;
use RLeroi\Debrid\Dtos\DebridFileDto;

interface ClientStrategy
{
    public function __construct(?string $token, ?ClientInterface $http = null);

    public function setToken(string $token): void;

    /**
     * Get list of cached files for a magnet link
     *
     * @param string $magnet The magnet link
     * @return DebridFileDto[] Array of file objects
     * @throws \RLeroi\Debrid\Exceptions\DebridException
     */
    public function getCachedFiles(string $magnet): array;

    /**
     * Check if a specific file exists in the cached torrent
     *
     * @param string $magnet The magnet link
     * @param string $path The file path to check
     * @return bool True if file exists
     * @throws \RLeroi\Debrid\Exceptions\DebridException
     */
    public function isFileCached(string $magnet, string $path): bool;

    /**
     * Get download link for a specific file
     *
     * @param string $magnet The magnet link
     * @param string $path The file path
     * @return string The download link
     * @throws \RLeroi\Debrid\Exceptions\DebridException
     */
    public function getLink(string $magnet, string $path): string;

    /**
     * Add a magnet link to the debrid service
     *
     * @param string $magnet The magnet link to add
     * @return string The torrent ID
     * @throws \RLeroi\Debrid\Exceptions\DebridException
     */
    public function addMagnet(string $magnet): string;
}
