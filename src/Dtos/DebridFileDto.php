<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Dtos;

/**
 * Represents a file from a debrid service
 */
final class DebridFileDto
{
    public function __construct(
        public string $path,
        public int    $size,
        public array  $data,
    )
    {
    }

    /**
     * Get the filename without directory path
     */
    public function getFilename(): string
    {
        return basename($this->path);
    }

    /**
     * Get the file extension
     */
    public function getExtension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
