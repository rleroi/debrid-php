<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Mappers;

use RLeroi\Debrid\DTOs\CacheCheckResult;
use RLeroi\Debrid\DTOs\DebridFile;
use RLeroi\Debrid\DTOs\DebridTorrent;

final class DebridLinkMapper implements MapperInterface
{
    public function mapFiles(array $response): array
    {
        // DebridLink torrents/check response format
        if (!isset($response['data']['files'])) {
            return [];
        }

        $files = [];
        foreach ($response['data']['files'] as $file) {
            $files[] = $this->mapFile($file);
        }

        return $files;
    }

    public function mapTorrent(string $magnet, array $response): ?DebridTorrent
    {
        // DebridLink torrents/check response format
        if (!isset($response['data'])) {
            return null;
        }

        $torrentData = $response['data'];

        // Parse files
        $files = [];
        if (isset($torrentData['files'])) {
            foreach ($torrentData['files'] as $file) {
                $files[] = $this->mapFile($file);
            }
        }

        return DebridTorrent::fromArray([
            'id' => $torrentData['torrent_id'] ?? '',
            'hash' => $torrentData['hash'] ?? '',
            'name' => $torrentData['name'] ?? '',
            'status' => $torrentData['status'] ?? '',
            'size' => $torrentData['size'] ?? 0,
            'files' => $files,
            'magnet' => $magnet,
            'upload_date' => $torrentData['created_at'] ?? null,
            'completion_date' => $torrentData['completed_at'] ?? null,
        ]);
    }

    public function mapFile(array $fileData): DebridFile
    {
        // DebridLink file format: {'path': 'filename', 'size': size, 'id': id}
        return new DebridFile(
            path: $fileData['path'] ?? '',
            size: (int) ($fileData['size'] ?? 0),
            id: $fileData['id'] ?? null,
            downloadLink: null, // DebridLink requires separate download call
        );
    }
}
