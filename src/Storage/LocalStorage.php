<?php

namespace App\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class LocalStorage implements StorageInterface
{
    private string $storagePath;

    public function __construct(string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? dirname(__DIR__, 2) . '/storage';
    }

    public function write(string $path, string $content): void
    {
        $fullPath = $this->getFullPath($path);
        $this->ensureDirectory(dirname($fullPath));
        file_put_contents($fullPath, $content);
    }

    public function writeStream(string $path, $resource): void
    {
        $fullPath = $this->getFullPath($path);
        $this->ensureDirectory(dirname($fullPath));
        $dest = fopen($fullPath, 'wb');
        stream_copy_to_stream($resource, $dest);
        fclose($dest);
    }

    public function read(string $path): string
    {
        return file_get_contents($this->getFullPath($path));
    }

    public function readStream(string $path): mixed
    {
        return fopen($this->getFullPath($path), 'rb');
    }

    public function delete(string $path): void
    {
        $fullPath = $this->getFullPath($path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        $this->removeEmptyDirectories(dirname($fullPath));
    }

    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    public function size(string $path): int
    {
        return filesize($this->getFullPath($path));
    }

    public function mimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($finfo, $this->getFullPath($path));
    }

    public function generatePath(string $originalFilename): string
    {
        $random = bin2hex(random_bytes(32));
        $shardA = substr($random, 0, 2);
        $shardB = substr($random, 2, 2);
        $shardC = substr($random, 4, 2);

        $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $safeExt = $ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '.bin';

        return sprintf('transfers/%s/%s/%s/%s%s', $shardA, $shardB, $shardC, $random, $safeExt);
    }

    public function uploadFile(UploadedFile $file): array
    {
        $path = $this->generatePath($file->getClientOriginalName());
        $fullPath = $this->getFullPath($path);
        $this->ensureDirectory(dirname($fullPath));

        $source = fopen($file->getPathname(), 'rb');
        $dest = fopen($fullPath, 'wb');
        stream_copy_to_stream($source, $dest);
        fclose($source);
        fclose($dest);

        $serverMime = $this->mimeType($path);
        $browserMime = $file->getMimeType();
        $finalMime = $serverMime ?: $browserMime;

        return [
            'path' => $path,
            'size' => $this->size($path),
            'mimeType' => $finalMime,
        ];
    }

    public function writeTextContent(string $content, string $filename): array
    {
        $path = $this->generatePath($filename . '.txt');
        $this->write($path, $content);

        return [
            'path' => $path,
            'size' => strlen($content),
            'mimeType' => 'text/plain',
        ];
    }

    /**
     * Move an already-existing file into LocalStorage's sharded layout. Used
     * by the resumable-upload finalizer to take a tus temp file and produce
     * a TransferFile.storedFilename that goes through the standard path.
     */
    public function ingestFromPath(string $sourcePath, string $originalFilename): array
    {
        $path = $this->generatePath($originalFilename);
        $fullPath = $this->getFullPath($path);
        $this->ensureDirectory(dirname($fullPath));

        $source = fopen($sourcePath, 'rb');
        $dest = fopen($fullPath, 'wb');
        stream_copy_to_stream($source, $dest);
        fclose($source);
        fclose($dest);

        return [
            'path' => $path,
            'size' => $this->size($path),
            'mimeType' => $this->mimeType($path),
        ];
    }

    private function getFullPath(string $path): string
    {
        return rtrim($this->storagePath, '/') . '/' . ltrim($path, '/');
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function removeEmptyDirectories(string $dir): void
    {
        $storageReal = realpath($this->storagePath);
        $dirReal = realpath($dir);
        if (!$dirReal || !$storageReal || $dirReal === $storageReal || str_starts_with($storageReal, $dirReal)) {
            return;
        }
        $files = scandir($dir);
        if (count($files) <= 2) {
            rmdir($dir);
            $this->removeEmptyDirectories(dirname($dir));
        }
    }
}
