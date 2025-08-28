<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Mappers;

use RLeroi\Debrid\DTOs\CacheCheckResult;
use RLeroi\Debrid\DTOs\DebridFile;
use RLeroi\Debrid\DTOs\DebridTorrent;

final class TorBoxMapper implements MapperInterface
{
    public function mapCacheCheck(string $hash, array $response): CacheCheckResult
    {
        // TorBox torrents/checkcached response format
        if (!isset($response['data'][$hash])) {
            return CacheCheckResult::notCached($hash);
        }

        $torrentData = $response['data'][$hash];
        
        // Parse files
        $files = [];
        if (isset($torrentData['files'])) {
            foreach ($torrentData['files'] as $file) {
                $files[] = $this->mapFile($file);
            }
        }

        return new CacheCheckResult(
            isCached: true,
            hash: $hash,
            files: $files,
            torrentId: $torrentData['torrent_id'] ?? null,
            totalSize: $torrentData['size'] ?? null,
        );
    }

    public function mapFiles(array $response): array
    {
        // TorBox torrents/checkcached response format
        $files = [];
        
        // Response can be either direct files array or nested under hash
        if (isset($response['files'])) {
            foreach ($response['files'] as $file) {
                $files[] = $this->mapFile($file);
            }
        } elseif (isset($response['data'])) {
            // Handle nested format
            foreach ($response['data'] as $hash => $torrentData) {
                if (isset($torrentData['files'])) {
                    foreach ($torrentData['files'] as $file) {
                        $files[] = $this->mapFile($file);
                    }
                }
            }
        }

        return $files;
    }

    public function mapTorrent(string $magnet, array $response): ?DebridTorrent
    {
        // TorBox torrents/info response format
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
        // TorBox file format: {'path': 'filename', 'size': size, 'id': id}
        return new DebridFile(
            path: $fileData['path'] ?? '',
            size: (int) ($fileData['size'] ?? 0),
            id: $fileData['id'] ?? null,
            downloadLink: null, // TorBox requires separate download call
        );
    }
}
