<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Mappers;

use RLeroi\Debrid\Dtos\CacheCheckResult;
use RLeroi\Debrid\Dtos\DebridFileDto;
use RLeroi\Debrid\Dtos\DebridTorrent;

final class TorBoxMapper implements MapperInterface
{
    public function mapFiles(array $response): array
    {

        $files = [];

       if (isset($response['data'])) {
            foreach ($response['data'] as $torrentData) {
                if (isset($torrentData['files'])) {
                    foreach ($torrentData['files'] as $file) {
                        $files[] = $this->mapFile($file);
                    }
                }
            }
        }

        return $files;
    }

    public function mapFile(array $fileData): DebridFileDto
    {
        // TorBox file format:
        //          "name": "Severance Season 1 Mp4 1080p/Severance S01E01.mp4",
        //          "size": 1392569476,
        //          "opensubtitles_hash": "56937691104d636a",
        //          "short_name": "Severance S01E01.mp4",
        //          "mimetype": "video/mp4"

        return new DebridFileDto(
            path: trim(preg_replace('/  +/', ' ', $fileData['name'] ?? '')), // strip multiple spaces
            size: (int) ($fileData['size'] ?? 0),
            data: $fileData,
        );
    }
}
