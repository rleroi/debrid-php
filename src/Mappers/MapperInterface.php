<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Mappers;

use RLeroi\Debrid\DTOs\DebridFile;

interface MapperInterface
{
    /**
     * Map API response to DebridFile array.
     */
    public function mapFiles(array $response): array;

    /**
     * Map single file from API response.
     */
    public function mapFile(array $fileData): DebridFile;
}
