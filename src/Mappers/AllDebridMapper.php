<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Mappers;

use RLeroi\Debrid\DTOs\DebridFile;

final class AllDebridMapper implements MapperInterface
{
    public function mapFiles(array $response): array
    {
        if (!isset($response['data']['magnets']['files'])) {
            return [];
        }

        $files = [];
        foreach ($response['data']['magnets']['files'] as $file) {
            $files = array_merge($files, $this->mapFileGroup($file));
        }

        return $files;
    }

    public function mapFile(array $fileData): DebridFile
    {
        // AllDebrid file format: {'n': 'filename', 's': size, 'l': 'link'}
        return new DebridFile(
            path: $fileData['n'] ?? '',
            size: (int) ($fileData['s'] ?? 0),
            data: $fileData,
        );
    }

    /**
     * todo test nested folders
     * Map a file group that might contain nested entries
     */
    private function mapFileGroup(array $fileGroup): array
    {
        $files = [];
        
        // Check if this is a nested structure with 'e' (entries) array
        if (isset($fileGroup['e']) && is_array($fileGroup['e'])) {
            // This is a nested structure, process each entry
            foreach ($fileGroup['e'] as $entry) {
                $files[] = $this->mapFile($entry);
            }
        } else {
            // This is a simple file structure
            $files[] = $this->mapFile($fileGroup);
        }
        
        return $files;
    }
}
