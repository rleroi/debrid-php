<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Clients;

use GuzzleHttp\ClientInterface;
use RLeroi\Debrid\Exceptions\DebridException;

interface ClientStrategy
{
    public function __construct(?string $token, ?ClientInterface $http = null);

    public function setToken(string $token): void;

    /**
     * @throws DebridException
     */
    public function getCachedFiles(string $magnet): array;

    /**
     * @throws DebridException
     */
    public function addMagnet(string $magnet): string;

    /**
     * @throws DebridException
     */
    public function isFileCached(string $magnet, string $path): bool;

    /**
     * @throws DebridException
     */
    public function getLink(string $magnet, string $path): string;
}
