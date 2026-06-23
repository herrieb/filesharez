<?php

namespace App\Storage;

interface StorageInterface
{
    public function write(string $path, string $content): void;
    public function writeStream(string $path, $resource): void;
    public function read(string $path): string;
    public function readStream(string $path): mixed;
    public function delete(string $path): void;
    public function exists(string $path): bool;
    public function size(string $path): int;
    public function mimeType(string $path): string;
    public function ingestFromPath(string $sourcePath, string $originalFilename): array;
}