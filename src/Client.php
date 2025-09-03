<?php

declare(strict_types=1);

namespace RLeroi\Debrid;

use InvalidArgumentException;
use RLeroi\Debrid\Clients\AllDebridClient;
use RLeroi\Debrid\Clients\ClientStrategy;
use RLeroi\Debrid\Clients\PremiumizeClient;
use RLeroi\Debrid\Clients\RealDebridClient;
use RLeroi\Debrid\DTOs\DebridFile;
use RLeroi\Debrid\Exceptions\DebridException;

//use RLeroi\Debrid\Clients\DebridLinkClient;
//use RLeroi\Debrid\Clients\TorBoxClient;

final class Client
{
    private ?ClientStrategy $client = null;
    private ?string $token = null;

    public function setClientRealDebrid(?string $token = null): self
    {
        if ($token) {
            $this->setToken($token);
        }

        $this->client = new RealDebridClient($this->token);

        return $this;
    }

    public function setClientPremiumize(?string $token = null): self
    {
        if ($token) {
            $this->setToken($token);
        }

        $this->client = new PremiumizeClient($this->token);

        return $this;
    }

    /*public function setClientTorBox(?string $token = null): self
    {
        if ($token) {
            $this->setToken($token);
        }

        $this->client = new TorBoxClient($this->token);

        return $this;
    }*/

    public function setClientAllDebrid(?string $token = null): self
    {
        if ($token) {
            $this->setToken($token);
        }

        $this->client = new AllDebridClient($this->token);

        return $this;
    }

    /*public function setClientDebridLink(?string $token = null): self
    {
        if ($token) {
            $this->setToken($token);
        }

        $this->client = new DebridLinkClient($this->token);

        return $this;
    }*/

    public function setToken(string $token): self
    {
        $this->token = $token;
        $this->client?->setToken($token);

        return $this;
    }

    private function validateBuilder(): void
    {
        if (!$this->client) {
            throw new InvalidArgumentException('No client provided');
        }
        if (!$this->token) {
            throw new InvalidArgumentException('No token provided');
        }
    }

    /**
     * Get all cached files from a torrent.
     *
     * @param string $magnet The magnet link
     *
     * @throws DebridException
     *
     * @return DebridFile[] Array of cached files
     */
    public function getCachedFiles(string $magnet): array
    {
        $this->validateBuilder();

        return $this->client->getCachedFiles($magnet);
    }

    /**
     * Check if a specific file is cached in a torrent.
     *
     * @param string $magnet The magnet link
     * @param string $path   The file path
     *
     * @throws DebridException
     *
     * @return bool True if file is cached
     */
    public function isFileCached(string $magnet, string $path): bool
    {
        $this->validateBuilder();

        return $this->client->isFileCached($magnet, $path);
    }

    /**
     * Add a magnet link to the debrid service.
     *
     * @param string $magnet The magnet link
     *
     * @throws DebridException
     *
     * @return string The torrent ID
     */
    public function addMagnet(string $magnet): string
    {
        $this->validateBuilder();

        return $this->client->addMagnet($magnet);
    }

    /**
     * Get download link for a specific file.
     *
     * @param string $magnet The magnet link
     * @param string $path   The file path
     *
     * @throws DebridException
     *
     * @return string The download link
     */
    public function getLink(string $magnet, string $path): string
    {
        $this->validateBuilder();

        return $this->client->getLink($magnet, $path);
    }
}
