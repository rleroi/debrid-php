<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Mappers;

use RLeroi\Debrid\Dtos\DebridFileDto;

final class PremiumizeMapper implements MapperInterface
{
    public function mapFiles(array $response): array
    {
        if (!isset($response['status']) || $response['status'] !== 'success' || !isset($response['content'])) {
            return [];
        }

        return array_map(fn(array $file): DebridFileDto => $this->mapFile($file), $response['content']);
    }

    public function mapFile(array $fileData): DebridFileDto
    {
        // Premiumize file format: {'path': 'filename', 'size': size}
        $rawPath = $fileData['path'] ?? '';

        $path = $this->stripRootFolder($rawPath);

        return new DebridFileDto(
            path: $path,
            size: (int)($fileData['size'] ?? 0),
            data: $fileData,
        );
    }

    /**
     * Strip the root folder from the path to keep consistency with other clients
     * So "SomeTorrent/file.mp4" becomes "file.mp4"
     */
    private function stripRootFolder(string $path): string
    {
        // If path contains a slash, it has a root folder
        if (strpos($path, '/') !== false) {
            // Split by first slash and return everything after it
            $parts = explode('/', $path, 2);
            return $parts[1] ?? $path;
        }

        return $path;
    }
}
