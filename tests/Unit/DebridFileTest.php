<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RLeroi\Debrid\Dtos\DebridFileDto;

final class DebridFileTest extends TestCase
{
    private DebridFileDto $debridFile;
    private string $testPath;
    private int $testSize;
    private array $testData;

    protected function setUp(): void
    {
        $this->testPath = 'test/video/movie.mp4';
        $this->testSize = 1024 * 1024 * 100; // 100MB
        $this->testData = ['id' => '123', 'name' => 'movie.mp4'];
        $this->debridFile = new DebridFileDto($this->testPath, $this->testSize, $this->testData);
    }

    public function testConstructor(): void
    {
        $this->assertEquals($this->testPath, $this->debridFile->path);
        $this->assertEquals($this->testSize, $this->debridFile->size);
        $this->assertEquals($this->testData, $this->debridFile->data);
    }

    public function testGetFilename(): void
    {
        $filename = $this->debridFile->getFilename();

        $this->assertEquals('movie.mp4', $filename);
    }

    public function testGetFilenameWithComplexPath(): void
    {
        $complexFile = new DebridFileDto('very/deep/nested/path/file.txt', 1024, []);
        
        $this->assertEquals('file.txt', $complexFile->getFilename());
    }

    public function testGetExtension(): void
    {
        $extension = $this->debridFile->getExtension();

        $this->assertEquals('mp4', $extension);
    }

    public function testGetExtensionWithNoExtension(): void
    {
        $noExtFile = new DebridFileDto('test/file', 1024, []);
        
        $this->assertEquals('', $noExtFile->getExtension());
    }

    public function testGetExtensionWithMultipleDots(): void
    {
        $multiDotFile = new DebridFileDto('test/file.backup.tar.gz', 1024, []);
        
        $this->assertEquals('gz', $multiDotFile->getExtension());
    }

    public function testGetFormattedSizeBytes(): void
    {
        $smallFile = new DebridFileDto('test/small.txt', 512, []);
        
        $this->assertEquals('512 B', $smallFile->getFormattedSize());
    }

    public function testGetFormattedSizeKB(): void
    {
        $kbFile = new DebridFileDto('test/medium.txt', 2048, []);
        
        $this->assertEquals('2 KB', $kbFile->getFormattedSize());
    }

    public function testGetFormattedSizeMB(): void
    {
        $mbFile = new DebridFileDto('test/large.txt', 2 * 1024 * 1024, []);
        
        $this->assertEquals('2 MB', $mbFile->getFormattedSize());
    }

    public function testGetFormattedSizeGB(): void
    {
        $gbFile = new DebridFileDto('test/huge.txt', 2 * 1024 * 1024 * 1024, []);
        
        $this->assertEquals('2 GB', $gbFile->getFormattedSize());
    }

    public function testGetFormattedSizeTB(): void
    {
        $tbFile = new DebridFileDto('test/massive.txt', 2 * 1024 * 1024 * 1024 * 1024, []);
        
        $this->assertEquals('2 TB', $tbFile->getFormattedSize());
    }

    public function testGetFormattedSizeWithDecimal(): void
    {
        $decimalFile = new DebridFileDto('test/decimal.txt', 1536, []);
        
        $this->assertEquals('1.5 KB', $decimalFile->getFormattedSize());
    }

    public function testGetFormattedSizeZero(): void
    {
        $zeroFile = new DebridFileDto('test/empty.txt', 0, []);
        
        $this->assertEquals('0 B', $zeroFile->getFormattedSize());
    }

    public function testGetFormattedSizeOriginalFile(): void
    {
        $this->assertEquals('100 MB', $this->debridFile->getFormattedSize());
    }
}
