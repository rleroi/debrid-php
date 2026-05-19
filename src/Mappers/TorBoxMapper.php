<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Mappers;

use RLeroi\Debrid\Dtos\DebridFileDto;

final class TorBoxMapper implements MapperInterface
{
    public function mapFiles(array $response): array
    {
        $files = [];
        $data = $response['data'] ?? null;
        if (!is_array($data) || $data === []) {
            return [];
        }

        foreach ($this->torrentRows($data) as $torrentRow) {
            if (!isset($torrentRow['files']) || !is_array($torrentRow['files'])) {
                continue;
            }

            foreach ($torrentRow['files'] as $file) {
                if (is_array($file)) {
                    $files[] = $this->mapFile($file);
                }
            }
        }

        return $files;
    }

    /**
     * TorBox wraps torrents differently per endpoint:
     * - checkcached / mylist (no id) often returns a list or hash => torrent.
     * - GET mylist?id=… returns a single torrent object in `data` (not a list).
     *
     * @return iterable<int, array<string, mixed>>
     */
    private function torrentRows(array $data): iterable
    {
        if (array_is_list($data)) {
            foreach ($data as $torrentRow) {
                if (is_array($torrentRow)) {
                    yield $torrentRow;
                }
            }

            return;
        }

        if (isset($data['files']) && is_array($data['files'])) {
            yield $data;

            return;
        }

        foreach ($data as $torrentRow) {
            if (is_array($torrentRow)) {
                yield $torrentRow;
            }
        }
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
