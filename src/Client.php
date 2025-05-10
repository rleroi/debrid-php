<?php

declare(strict_types=1);

namespace RLeroi\Debrid;

use InvalidArgumentException;
use RLeroi\Debrid\Clients\ClientStrategy;
use RLeroi\Debrid\Clients\PremiumizeClient;
use RLeroi\Debrid\Clients\RealDebridClient;
use RLeroi\Debrid\Clients\TorBoxClient;
use RLeroi\Debrid\Exceptions\DebridException;

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

    public function setClientTorBox(?string $token = null): self
    {
        if ($token) {
            $this->setToken($token);
        }

        $this->client = new TorBoxClient($this->token);

        return $this;
    }

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
     * @return array{path: string, size: int}[]
     * @throws DebridException
     */
    public function getCachedFiles(string $magnet): array
    {
        $this->validateBuilder();

        return $this->client->getCachedFiles($magnet);
    }

    /**
     * @throws DebridException
     */
    public function isFileCached(string $magnet, string $path): bool
    {
        $this->validateBuilder();

        return $this->client->isFileCached($magnet, $path);
    }

    /**
     * @throws DebridException
     */
    public function getLink(string $magnet, string $path): string
    {
        $this->validateBuilder();

        return $this->client->getLink($magnet, $path);
    }

    /**
     * @throws DebridException
     */
    public function addMagnet(string $magnet): string
    {
        $this->validateBuilder();

        return $this->client->addMagnet($magnet);
    }
}
