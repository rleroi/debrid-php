<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Mappers;

use RLeroi\Debrid\DTOs\DebridFile;

final class RealDebridMapper implements MapperInterface
{
    public function mapFiles(array $response): array
    {
        // Real-Debrid torrents/info response format
        if (!isset($response['files'])) {
            return [];
        }

        $files = [];
        foreach ($response['files'] as $file) {
            $files[] = $this->mapFile($file);
        }

        return $files;
    }

    public function mapFile(array $fileData): DebridFile
    {
        // Real-Debrid file format: {'path': '/filename', 'bytes': size, 'id': id}
        $path = $fileData['path'] ?? '';
        $path = ltrim($path, '/'); // Remove leading slash for consistency

        return new DebridFile(
            path: $path,
            size: (int)($fileData['bytes'] ?? 0),
            data: $fileData,
        );
    }
}
